<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Passkey;


use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Awf\Uri\Uri;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Cose\Algorithms;
use GuzzleHttp\Psr7\HttpFactory;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Exception\InvalidDataException;
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

final class Authentication implements AuthenticationInterface
{
	/**
	 * The credentials repository
	 *
	 * @var   PublicKeyCredentialSourceRepository
	 * @since 1.2.3
	 */
	protected PublicKeyCredentialSourceRepository $credentialsRepository;

	private readonly LoggerInterface $logger;

	public function __construct(?PublicKeyCredentialSourceRepository $credRepo = null, ?LoggerInterface $logger = null)
	{
		$this->logger                = $logger ?? Factory::getContainer()->loggerFactory->get('passkey');
		$this->credentialsRepository = $credRepo ?? new CredentialRepository();
	}

	final public static function create(
		?PublicKeyCredentialSourceRepository $credRepo = null
	)
	{
		$credRepo ??= new CredentialRepository();

		return new self($credRepo);
	}

	/**
	 * Returns the Public Key credential source repository object
	 *
	 * @return  PublicKeyCredentialSourceRepository|null
	 *
	 * @since   1.2.3
	 */
	final public function getCredentialsRepository(): ?PublicKeyCredentialSourceRepository
	{
		return $this->credentialsRepository;
	}

	/**
	 * Returns a User Entity object given a Panopticon  user
	 *
	 * @param   User  $user  The Panopticon user to get the user entity for
	 *
	 * @return  PublicKeyCredentialUserEntity
	 *
	 * @throws  InvalidDataException
	 * @since   1.2.3
	 */
	final public function getUserEntity(User $user): PublicKeyCredentialUserEntity
	{
		$repository = $this->credentialsRepository;

		return new PublicKeyCredentialUserEntity(
			$user->getUsername(),
			$repository->getHandleFromUserId($user->getId()),
			$user->getName(),
			$user->getAvatar(64)
		);
	}

	/**
	 * Try to find the site's favicon in the site's root, images, media, templates or current
	 * template directory.
	 *
	 * @return  string|null
	 *
	 * @since   1.2.3
	 */
	final protected function getSiteIcon(): ?string
	{
		$filenames = [
			'apple-touch-icon.png',
			'apple_touch_icon.png',
			'favicon.ico',
			'favicon.png',
			'favicon.gif',
			'favicon.bmp',
			'favicon.jpg',
			'favicon.svg',
		];

		try
		{
			$paths = [
				'/',
				'/media/',
				'/templates/',
				'/templates/' . Factory::getApplication()->getTemplate(),
			];
		}
		catch (\Exception)
		{
			return null;
		}

		foreach ($paths as $path)
		{
			foreach ($filenames as $filename)
			{
				$relFile  = $path . $filename;
				$filePath = APATH_BASE . $relFile;

				if (is_file($filePath))
				{
					break 2;
				}

				$relFile = null;
			}
		}

		if (!isset($relFile))
		{
			return null;
		}

		return rtrim(Uri::base(), '/') . '/' . ltrim($relFile, '/');
	}

	/**
	 * Returns an array of the PK credential descriptors (registered authenticators) for the given
	 * user.
	 *
	 * @param   User  $user  The Panopticon user to get the PK descriptors for
	 *
	 * @return  PublicKeyCredentialDescriptor[]
	 *
	 * @since   1.2.3
	 */
	final protected function getPubKeyDescriptorsForUser(User $user): array
	{
		$userEntity  = $this->getUserEntity($user);
		$repository  = $this->credentialsRepository;
		$descriptors = [];
		$records     = $repository->findAllForUserEntity($userEntity);

		foreach ($records as $record)
		{
			$descriptors[] = $record->getPublicKeyCredentialDescriptor();
		}

		return $descriptors;
	}


	/**
	 * @inheritDoc
	 */
	final public function getPubKeyCreationOptions(User $user, bool $resident = false
	): PublicKeyCredentialCreationOptions
	{
		$siteName = Factory::getContainer()->appConfig->get('fromname', 'Panopticon');

		// Relaying Party – Our site
		$rpEntity = new PublicKeyCredentialRpEntity(
			$siteName,
			Uri::getInstance()->toString(['host']),
			$this->getSiteIcon()
		);

		// User Entity
		$userEntity = $this->getUserEntity($user);

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
		$records                      = $this->getCredentialsRepository()->findAllForUserEntity($userEntity);

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
				$resident
					? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
					: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_DISCOURAGED
			)
			->setUserVerification(
				AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED
			);

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
			'passkey.publicKeyCredentialCreationOptions',
			base64_encode(serialize($publicKeyCredentialCreationOptions))
		);
		$session->set('passkey.registration_user_id', $user->getId());

		return $publicKeyCredentialCreationOptions;
	}

	/**
	 * @inheritDoc
	 */
	final public function validateAttestationResponse(string $data): PublicKeyCredentialSource
	{
		$container = Factory::getContainer();
		$session   = $container->segment;
		$lang      = $container->language;

		// Retrieve the PublicKeyCredentialCreationOptions object created earlier and perform sanity checks
		$encodedOptions = $session->get('passkey.publicKeyCredentialCreationOptions', null);

		if (empty($encodedOptions))
		{
			$this->logger->notice(
				'Cannot retrieve passkey.publicKeyCredentialCreationOptions from the session'
			);

			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_NO_PK'));
		}

		/** @var PublicKeyCredentialCreationOptions|null $publicKeyCredentialCreationOptions */
		try
		{
			$publicKeyCredentialCreationOptions = unserialize(base64_decode($encodedOptions));
		}
		catch (\Exception)
		{
			$this->logger->notice(
				'The passkey.publicKeyCredentialCreationOptions in the session is invalid'
			);

			$publicKeyCredentialCreationOptions = null;
		}

		if (!is_object($publicKeyCredentialCreationOptions)
		    || !($publicKeyCredentialCreationOptions instanceof PublicKeyCredentialCreationOptions))
		{
			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_NO_PK'));
		}

		// Retrieve the stored user ID and make sure it's the same one in the request.
		$storedUserId = $session->get('passkey.registration_user_id', 0);
		$myUser       = $container->userManager->getUser();
		$myUserId     = $myUser->getId();

		if (!$myUser->getId() || $myUserId != $storedUserId)
		{
			$this->logger->notice(
				sprintf(
					'Invalid user! We asked the authenticator to attest user ID %d, the current user ID is %d',
					$storedUserId, $myUserId
				)
			);

			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_INVALID_USER'));
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
		$attestationStatementSupportManager->add(new TPMAttestationStatementSupport());
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
			$this->getCredentialsRepository(),
			$tokenBindingHandler,
			$extensionOutputCheckerHandler
		);

		$authenticatorAttestationResponseValidator->setLogger($this->logger);

		// Note: Any Throwable from this point will bubble up to the GUI

		// Initialise a PSR-7 request object using Laminas Diactoros
		$request = (new HttpFactory())->createServerRequest('', Uri::current(), $_SERVER);

		// Load the data
		$publicKeyCredential = $publicKeyCredentialLoader->load(
			base64_decode(
				$this->reshapeRegistrationData($data)
			)
		);
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

	final public function getPubkeyRequestOptions(): ?PublicKeyCredentialRequestOptions
	{
		$this->logger->debug('Creating PK request options');

		$container = Factory::getContainer();
		$session   = $container->segment;
		$challenge = random_bytes(32);

		// Extensions
		$extensions = new AuthenticationExtensionsClientInputs();

		// Public Key Credential Request Options
		$publicKeyCredentialRequestOptions = (new PublicKeyCredentialRequestOptions($challenge))
			->setTimeout(60000)
			->allowCredentials(... [])
			->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED)
			->setExtensions($extensions);

		// Save in session. This is used during the verification stage to prevent replay attacks.
		$session->set(
			'passkey.publicKeyCredentialRequestOptions', base64_encode(serialize($publicKeyCredentialRequestOptions))
		);

		return $publicKeyCredentialRequestOptions;
	}

	final public function validateAssertionResponse(string $data): PublicKeyCredentialSource
	{
		$container = Factory::getContainer();
		$session   = $container->segment;
		$lang      = $container->language;

		// Make sure the public key credential request options in the session are valid
		$encodedPkOptions                  = $session->get('passkey.publicKeyCredentialRequestOptions', null);
		$serializedOptions                 = base64_decode($encodedPkOptions);
		$publicKeyCredentialRequestOptions = unserialize($serializedOptions);

		if (!is_object($publicKeyCredentialRequestOptions)
		    || empty($publicKeyCredentialRequestOptions)
		    || !($publicKeyCredentialRequestOptions instanceof PublicKeyCredentialRequestOptions))
		{
			$this->logger->notice(
				'Cannot retrieve valid passkey.publicKeyCredentialRequestOptions from the session'
			);

			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		$data = base64_decode($data);

		if (empty($data))
		{
			$this->logger->notice(
				'No or invalid assertion data received from the browser'
			);

			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

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
			$this->getCredentialsRepository(),
			$tokenBindingHandler,
			$extensionOutputCheckerHandler,
			$coseAlgorithmManager
		);

		$authenticatorAssertionResponseValidator->setLogger($this->logger);

		// Initialise a PSR-7 request object using Laminas Diactoros
		$request = (new HttpFactory())->createServerRequest('', Uri::current(), $_SERVER);

		// Load the data
		$publicKeyCredential = $publicKeyCredentialLoader->load(
			$this->reshapeValidationData($data)
		);
		$response            = $publicKeyCredential->getResponse();

		// Check if the response is an Authenticator Assertion Response
		if (!$response instanceof AuthenticatorAssertionResponse)
		{
			throw new RuntimeException('Not an authenticator assertion response');
		}

		/** @var AuthenticatorAssertionResponse $authenticatorAssertionResponse */
		$authenticatorAssertionResponse = $publicKeyCredential->getResponse();

		return $authenticatorAssertionResponseValidator->check(
			$publicKeyCredential->getRawId(),
			$authenticatorAssertionResponse,
			$publicKeyCredentialRequestOptions,
			$request,
			null
		);
	}

	/**
	 * Reshape the PassKey validation data.
	 *
	 * Some of the data returned from the browser are encoded using regular Base64 (instead of URL-safe Base64) and/or
	 * have padding. The WebAuthn library requires all data to be encoded using the URL-safe Base64 algorithm *without*
	 * padding.
	 *
	 * This method will safely convert between the actual and the desired format.
	 *
	 * @param   string  $data
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function reshapeValidationData(string $data): string
	{
		$decodedData = @json_decode($data);

		if (empty($decodedData) || !is_object($decodedData))
		{
			return $data;
		}

		if ($decodedData->id ?? null)
		{
			$decodedData->id = Base64UrlSafe::encodeUnpadded(Base64UrlSafe::decode($decodedData->id));
		}

		if ($decodedData->rawId ?? null)
		{
			$decodedData->rawId = Base64::encodeUnpadded(Base64UrlSafe::decode($decodedData->id));
		}

		if (!is_object($decodedData->response ?? null))
		{
			return json_encode($decodedData);
		}

		foreach ($decodedData->response as $key => $value)
		{

			$decodedData->response->{$key} = Base64UrlSafe::encodeUnpadded(
				Base64::decode($decodedData->response->{$key})
			);
		}

		return json_encode($decodedData);
	}

	/**
	 * Reshape the PassKey registration data.
	 *
	 * Some of the data returned from the browser are encoded using regular Base64 (instead of URL-safe Base64) and/or
	 * have padding. The WebAuthn library requires all data to be encoded using the URL-safe Base64 algorithm *without*
	 * padding.
	 *
	 * This method will safely convert between the actual and the desired format.
	 *
	 * @param   string  $data
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function reshapeRegistrationData(string $data): string
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
					$clientDataJSON->challenge = Base64UrlSafe::encodeUnpadded(
						Base64UrlSafe::decode($clientDataJSON->challenge)
					);

					$decodedData->response->clientDataJSON = Base64UrlSafe::encodeUnpadded(
						json_encode($clientDataJSON)
					);
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