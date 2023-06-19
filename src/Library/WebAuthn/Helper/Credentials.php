<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\WebAuthn\Helper;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Cose\Algorithms;
use Exception;
use GuzzleHttp\Psr7\HttpFactory;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AttestedCredentialData;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;

defined('AKEEBA') || die;

class Credentials
{
	public function __construct(
		private readonly PublicKeyCredentialSourceRepository $repository, private ?LoggerInterface $logger
	)
	{
		$this->logger = $this->logger ?? Factory::getContainer()->loggerFactory->get('mfa_webauthn');
	}

	/**
	 * Creates a WebAuthn Public Key Credential Request (challenge) used by the browser during key verification
	 *
	 * @param   int  $user_id
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function createChallenge(int $user_id): string
	{
		// Load the saved credentials into an array of PublicKeyCredentialDescriptor objects
		try
		{
			$userEntity  = new PublicKeyCredentialUserEntity(
				'', $user_id, ''
			);
			$credentials = $this->repository->findAllForUserEntity($userEntity);
		}
		catch (Exception $e)
		{
			return json_encode(false);
		}

		// No stored credentials?
		if (empty($credentials))
		{
			return json_encode(false);
		}

		$registeredPublicKeyCredentialDescriptors = [];

		/** @var PublicKeyCredentialSource $record */
		foreach ($credentials as $record)
		{
			try
			{
				$registeredPublicKeyCredentialDescriptors[] = $record->getPublicKeyCredentialDescriptor();
			}
			catch (Throwable $e)
			{
				continue;
			}
		}

		$challenge = random_bytes(32);

		// Extensions
		$extensions = new AuthenticationExtensionsClientInputs;

		// Public Key Credential Request Options
		$publicKeyCredentialRequestOptions = (new PublicKeyCredentialRequestOptions($challenge))
			->setTimeout(60000)
			->allowCredentials(... $registeredPublicKeyCredentialDescriptors)
			->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED)
			->setExtensions($extensions);

		// Save in session. This is used during the verification stage to prevent replay attacks.
		$session = Factory::getContainer()->segment;
		$session->set(
			'mfa_webauthn.publicKeyCredentialRequestOptions',
			base64_encode(serialize($publicKeyCredentialRequestOptions))
		);
		$session->set('mfa_webauthn.userHandle', $user_id);
		$session->set('mfa_webauthn.userId', $user_id);

		// Return the JSON encoded data to the caller
		return json_encode($publicKeyCredentialRequestOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Checks if the browser's response to our challenge is valid.
	 *
	 * @param   string  $response  Base64-encoded response
	 *
	 * @throws  Exception|Throwable  When something does not check out.
	 * @since   1.0.0
	 *
	 */
	public function validateChallenge(string $response): void
	{
		$session = Factory::getContainer()->segment;

		$encodedPkOptions = $session->get('mfa_webauthn.publicKeyCredentialRequestOptions', null);
		$userHandle       = $session->get('mfa_webauthn.userHandle', null);
		$userId           = $session->get('mfa_webauthn.userId', null);

		$session->set('mfa_webauthn.publicKeyCredentialRequestOptions', null);
		$session->set('mfa_webauthn.userHandle', null);
		$session->set('mfa_webauthn.userId', null);

		if (empty($userId))
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		// Make sure the user exists
		if (Factory::getContainer()->userManager->getUser($userId)->getId() != $userId)
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		// Make sure the user is ourselves (we cannot perform 2SV on behalf of another user!)
		if (Factory::getContainer()->userManager->getUser()->getId() != $userId)
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		// Make sure the public key credential request options in the session are valid
		$serializedOptions                 = base64_decode($encodedPkOptions);
		$publicKeyCredentialRequestOptions = unserialize($serializedOptions);

		if (!is_object($publicKeyCredentialRequestOptions) || empty($publicKeyCredentialRequestOptions) ||
			!($publicKeyCredentialRequestOptions instanceof PublicKeyCredentialRequestOptions))
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		// Unserialize the browser response data
		$data = base64_decode($response);

		// Cose Algorithm Manager
		$coseAlgorithmManager = (new Manager)
			->add(new ECDSA\ES256)
			->add(new ECDSA\ES512)
			->add(new EdDSA\EdDSA)
			->add(new RSA\RS1)
			->add(new RSA\RS256)
			->add(new RSA\RS512);

		// Attestation Statement Support Manager
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add(new NoneAttestationStatementSupport());
		$attestationStatementSupportManager->add(new AndroidKeyAttestationStatementSupport());
		$attestationStatementSupportManager->add(new AppleAttestationStatementSupport());
		$attestationStatementSupportManager->add(new FidoU2FAttestationStatementSupport());
		$attestationStatementSupportManager->add(new TPMAttestationStatementSupport);
		$attestationStatementSupportManager->add(new PackedAttestationStatementSupport($coseAlgorithmManager));

		// Attestation Object Loader
		$attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
		$attestationObjectLoader->setLogger($this->logger);

		// Public Key Credential Loader
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);
		$publicKeyCredentialLoader->setLogger($this->logger);

		// The token binding handler
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();

		// Extension Output Checker Handler
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler;

		// Authenticator Assertion Response Validator
		$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
			$this->repository,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler,
			$coseAlgorithmManager
		);
		$authenticatorAssertionResponseValidator->setLogger($this->logger);

		// Initialise a PSR-7 request object using Guzzle
		$request = (new HttpFactory())->createServerRequest('', Uri::current(), $_SERVER);

		// Load the data
		$publicKeyCredential = $publicKeyCredentialLoader->load($data);
		$response            = $publicKeyCredential->getResponse();

		// Check if the response is an Authenticator Assertion Response
		if (!$response instanceof AuthenticatorAssertionResponse)
		{
			throw new RuntimeException('Not an authenticator assertion response');
		}

		/** @var AuthenticatorAssertionResponse $authenticatorAssertionResponse */
		$authenticatorAssertionResponse = $publicKeyCredential->getResponse();
		$authenticatorAssertionResponseValidator->check(
			$publicKeyCredential->getRawId(),
			$authenticatorAssertionResponse,
			$publicKeyCredentialRequestOptions,
			$request,
			$userHandle
		);
	}

	/**
	 * Create a public key for credentials creation.
	 *
	 * The result is a JSON string which can be used in Javascript code with navigator.credentials.create().
	 *
	 * Unfortunately, feature detection is not possible at the client side before making the request to link an
	 * authenticator. Therefore, you need to call this method with all three authenticator options and let the user
	 * decide which one to use based on the available platform and hardware at hand.
	 *
	 * @param   User  $user  The user to create the public key for
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function createPublicKey(User $user): string
	{
		$siteName = Text::_('PANOPTICON_APP_TITLE');

		// Relaying Party -- Our site
		$rpEntity = new PublicKeyCredentialRpEntity(
			$siteName,
			Uri::getInstance()->toString(['host'])
		);

		// User Entity
		$userEntity = new PublicKeyCredentialUserEntity(
			$user->getUsername(),
			$user->getId(),
			$user->getName(),
			$user->getAvatar(64)
		);

		// Challenge
		$challenge = random_bytes(32);

		// Public Key Credential Parameters
		$publicKeyCredentialParametersList = [
			// Prefer ECDSA (keys based on Elliptic Curve Cryptography with NIST P-521, P-384 or P-256)
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES512),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES384),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
			// Fall back to RSASSA-PSS when ECC is not available. Minimal storage for resident keys available for these.
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_PS512),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_PS384),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_PS256),
			// Shared secret w/ HKDF and SHA-512
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_DIRECT_HKDF_SHA_512),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_DIRECT_HKDF_SHA_256),
			// Shared secret w/ AES-MAC 256-bit key
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_DIRECT_HKDF_AES_256),
		];

		// If libsodium is enabled prefer Edwards-curve Digital Signature Algorithm (EdDSA)
		if (function_exists('sodium_crypto_sign_seed_keypair'))
		{
			array_unshift(
				$publicKeyCredentialParametersList,
				new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_EDDSA)
			);
		}

		// Timeout: 60 seconds (given in milliseconds)
		$timeout = 60000;

		// Devices to exclude (already set up authenticators)
		$excludedPublicKeyDescriptors = [];
		$records                      = $this->repository->findAllForUserEntity($userEntity);

		foreach ($records as $record)
		{
			$excludedPublicKeyDescriptors[] = new PublicKeyCredentialDescriptor(
				$record->getType(), $record->getCredentialPublicKey()
			);
		}

		$authenticatorAttachment = AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE;

		// Authenticator Selection Criteria (we used default values)
		$authenticatorSelectionCriteria = (new AuthenticatorSelectionCriteria())
			->setAuthenticatorAttachment($authenticatorAttachment)
			->setResidentKey(
				AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
			)
			->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED);

		// Extensions (not yet supported by the library)
		$extensions = new AuthenticationExtensionsClientInputs;

		// Attestation preference
		$attestationPreference = PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;

		// Public key credential creation options
		$publicKeyCredentialCreationOptions = new PublicKeyCredentialCreationOptions(
			$rpEntity,
			$userEntity,
			$challenge,
			$publicKeyCredentialParametersList
		);
		$publicKeyCredentialCreationOptions->setTimeout($timeout);
		$publicKeyCredentialCreationOptions->excludeCredentials(...$excludedPublicKeyDescriptors);
		$publicKeyCredentialCreationOptions->setAuthenticatorSelection($authenticatorSelectionCriteria);
		$publicKeyCredentialCreationOptions->setAttestation($attestationPreference);
		$publicKeyCredentialCreationOptions->setExtensions($extensions);

		// Save data in the session
		$session = Factory::getContainer()->segment;

		$session->set(
			'mfa_webauthn.publicKeyCredentialCreationOptions',
			base64_encode(serialize($publicKeyCredentialCreationOptions))
		);
		$session->set('mfa_webauthn.registration_user_id', $user->getId());

		return json_encode($publicKeyCredentialCreationOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Validate the authentication data returned by the device and return the attested credential data on success.
	 *
	 * An exception will be returned on error. Also, under very rare conditions, you may receive NULL instead of
	 * attested credential data which means that something was off in the returned data from the browser.
	 *
	 * @param   string  $data  The JSON-encoded data returned by the browser during the authentication flow
	 *
	 * @return  AttestedCredentialData|null
	 * @since   1.0.0
	 */
	public function validateAuthenticationData(string $data): ?PublicKeyCredentialSource
	{
		$session = Factory::getContainer()->segment;

		// Retrieve the PublicKeyCredentialCreationOptions object created earlier and perform sanity checks
		$encodedOptions = $session->get('mfa_webauthn.publicKeyCredentialCreationOptions', null);

		if (empty($encodedOptions))
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_NO_PK'));
		}

		try
		{
			$publicKeyCredentialCreationOptions = unserialize(base64_decode($encodedOptions));
		}
		catch (Exception $e)
		{
			$publicKeyCredentialCreationOptions = null;
		}

		if (
			!is_object($publicKeyCredentialCreationOptions) ||
			!($publicKeyCredentialCreationOptions instanceof PublicKeyCredentialCreationOptions)
		)
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_NO_PK'));
		}

		// Retrieve the stored user ID and make sure it's the same one in the request.
		$storedUserId = $session->get('mfa_webauthn.registration_user_id', 0);
		$myUser       = Factory::getContainer()->userManager->getUser();
		$myUserId     = $myUser->getId();

		if (($myUser->getId() <= 0) || ($myUserId != $storedUserId))
		{
			throw new RuntimeException(Text::_('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_USER'));
		}

		// Cose Algorithm Manager
		$coseAlgorithmManager = (new Manager())
			->add(new ECDSA\ES256())
			->add(new ECDSA\ES512())
			->add(new EdDSA\EdDSA())
			->add(new RSA\RS1())
			->add(new RSA\RS256())
			->add(new RSA\RS512());

		// The token binding handler
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();

		// Attestation Statement Support Manager
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add(new NoneAttestationStatementSupport());
		$attestationStatementSupportManager->add(new AndroidKeyAttestationStatementSupport());
		$attestationStatementSupportManager->add(new AppleAttestationStatementSupport());
		$attestationStatementSupportManager->add(new FidoU2FAttestationStatementSupport());
		$attestationStatementSupportManager->add(new TPMAttestationStatementSupport);
		$attestationStatementSupportManager->add(new PackedAttestationStatementSupport($coseAlgorithmManager));

		// Attestation Object Loader
		$attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
		$attestationObjectLoader->setLogger($this->logger);

		// Public Key Credential Loader
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);
		$publicKeyCredentialLoader->setLogger($this->logger);

		// Extension output checker handler
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();

		// Authenticator Attestation Response Validator
		$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
			$attestationStatementSupportManager,
			$this->repository,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler
		);
		$authenticatorAttestationResponseValidator->setLogger($this->logger);

		// Any Throwable from this point will bubble up to the GUI

		// Initialise a PSR-7 request object using Guzzle
		$request = (new HttpFactory())->createServerRequest('', Uri::current(), $_SERVER);

		// Load the data

		$data = $this->reshapeRegistrationData($data);

		$publicKeyCredential = $publicKeyCredentialLoader->load(base64_decode($data));
		$response            = $publicKeyCredential->getResponse();

		// Check if the response is an Authenticator Attestation Response
		if (!$response instanceof AuthenticatorAttestationResponse)
		{
			throw new RuntimeException('Not an authenticator attestation response');
		}

		// Check the response against the request
		$authenticatorAttestationResponseValidator->check($response, $publicKeyCredentialCreationOptions, $request);

		/**
		 * Everything is OK here. You can get the Public Key Credential Source. This object should be persisted using
		 * the Public Key Credential Source repository.
		 */
		return $authenticatorAttestationResponseValidator->check(
			$response, $publicKeyCredentialCreationOptions, $request
		);
	}

	private function reshapeRegistrationData(string $data)
	{
		$json = @Base64UrlSafe::decode($data);

		if ($json === false)
		{
			return $data;
		}

		$decodedData = @json_decode($json);

		if (empty($decodedData) || !is_object($decodedData))
		{
			return $data;
		}

		if (!isset($decodedData->response) || !is_object($decodedData->response))
		{
			return $data;
		}

		$clientDataJSON = $decodedData->response->clientDataJSON ?? null;

		if ($clientDataJSON)
		{
			$json = Base64UrlSafe::decode($clientDataJSON);

			if ($json !== false)
			{
				$clientDataJSON = @json_decode($json);

				if (!empty($clientDataJSON) && is_object($clientDataJSON) && isset($clientDataJSON->challenge))
				{
					$clientDataJSON->challenge = Base64UrlSafe::encodeUnpadded(Base64UrlSafe::decode($clientDataJSON->challenge));

					$decodedData->response->clientDataJSON = Base64UrlSafe::encodeUnpadded(json_encode($clientDataJSON));
				}

			}
		}

		$attestationObject = $decodedData->response->attestationObject ?? null;

		if ($attestationObject)
		{
			$decoded = Base64::decode($attestationObject);

			if ($decoded !== false)
			{
				$decodedData->response->attestationObject = Base64UrlSafe::encodeUnpadded($decoded);
			}
		}

		return Base64UrlSafe::encodeUnpadded(json_encode($decodedData));
	}
}