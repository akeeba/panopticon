<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth\Plugin;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Library\View\FakeView;
use Akeeba\Panopticon\Library\WebAuthn\Helper\Credentials;
use Akeeba\Panopticon\Library\WebAuthn\Repository\MFA as PasskeyRepositoryMFA;
use Akeeba\Panopticon\Model\Mfa;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Event\Observable;
use Awf\Event\Observer;
use Awf\Input\Input;
use Awf\Text\Language;
use Awf\Text\LanguageAwareInterface;
use Awf\Text\LanguageAwareTrait;
use Awf\Uri\Uri;
use Exception;
use RuntimeException;
use Webauthn\PublicKeyCredentialRequestOptions;

class PassKeys
	extends Observer
	implements ContainerAwareInterface, LanguageAwareInterface
{
	use ContainerAwareTrait;
	use LanguageAwareTrait;

	private const METHOD_NAME = 'passkeys';

	private const HELP_URL = 'https://github.com/akeeba/panopticon/wiki/MFA-PassKeys';

	public function __construct(Observable &$subject, ?Container $container = null, ?Language $language = null)
	{
		parent::__construct($subject);

		$this->setContainer($container ?? Factory::getContainer());
		$this->setLanguage($language ?? $this->getContainer()->language);
	}

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 */
	public function onMfaGetMethod(): array
	{
		return [
			'name'               => self::METHOD_NAME,
			'display'            => $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_DISPLAYEDAS'),
			'shortinfo'          => $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_SHORTINFO'),
			//'image'              => 'media/mfa/images/final-webauthn-logo-webauthn-color.png',
			'image'              => 'media/mfa/images/passkey.svg',
			'allowMultiple'      => true,
			'allowEntryBatching' => true,
			'help_url'           => self::HELP_URL,
		];
	}

	/**
	 * Returns the information used to render the MFA setup page.
	 *
	 * This is the page which allows the user to add or modify a MFA method for their user account. If the record does
	 * not correspond to your plugin, return an empty array.
	 *
	 * @param   Mfa  $record  The #__mfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onMfaGetSetup(Mfa $record): array
	{
		// Make sure we are actually meant to handle this Method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		// Get some values assuming that we are NOT setting up U2F (the key is already registered)
		$submitClass = '';
		$preMessage  = $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_CONFIGURED');
		$type        = 'input';
		$html        = '';
		$htmlButton  = '';
		$helpURL     = self::HELP_URL;
		$hiddenData  = [];
		$options     = $record->getOptions();

		/**
		 * If there are no authenticators set up yet, I need to show a different message and take a different action
		 * when the user clicks the submit button.
		 */
		if (
			!is_array($options)
			|| empty($options)
			|| !isset($options['credentialId'])
			|| empty($options['credentialId'])
		)
		{
			$document = $this->getContainer()->application->getDocument();

			$document->addScript(
				Uri::base() . 'media/js/webauthn-mfa.min.js',
				defer: true
			);

			$fakeView = new FakeView(
				$this->getContainer(), [
					'name' => 'Passkeymfa',
				]
			);

			$html       = $fakeView->loadAnyTemplate('Passkeysmfa/register');
			$htmlButton = $fakeView->loadAnyTemplate('Passkeysmfa/register_button');
			$type       = 'custom';


			// Load JS translations
			$document->lang('PANOPTICON_MFA_PASSKEYS_ERR_NOTAVAILABLE_HEAD');

			$document->addScriptOptions('mfa.pagetype', 'setup', false);

			// Save the WebAuthn request to the session
			$user        = $this->getContainer()->userManager->getUser();
			$repository  = new PasskeyRepositoryMFA($user->getId());
			$credentials = new Credentials($repository, $this->getContainer()->logger, $this->getContainer(), $this->getLanguage());
			/** @noinspection PhpParamsInspection */
			$hiddenData['pkRequest'] = base64_encode($credentials->createPublicKey($user));

			// Special button handling
			$submitClass = "mfa_passkey_setup";

			// Message to display
			$preMessage = $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_INSTRUCTIONS');
		}

		// Render the pre-message in a properly padded container
		$preMessage = (new FakeView(
			$this->getContainer(),
			[
				'name' => 'Passkeymfa',
			]
		))
			->loadAnyTemplate(
				'Passkeysmfa/pre_message',
				[
					'preMessage' => $preMessage,
				]
			);

		return [
			'default_title' => $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_DISPLAYEDAS'),
			'pre_message'   => $preMessage,
			'table_heading' => '',
			'tabular_data'  => [],
			'hidden_data'   => $hiddenData,
			'field_type'    => $type,
			'input_type'    => 'hidden',
			'html'          => $html,
			'html_button'   => $htmlButton,
			'show_submit'   => false,
			'submit_class'  => $submitClass,
			'help_url'      => $helpURL,
		];
	}

	/**
	 * Parse the input from the MFA setup page.
	 *
	 * Return the configuration information to be saved to the database.
	 *
	 * If the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   Mfa    $record  The #__mfa record currently selected by the user.
	 * @param   Input  $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 */
	public function onMfaSaveSetup(Mfa $record, Input $input): array
	{
		$defaultOptions = $record->getOptions();

		// Make sure we are actually meant to handle this Method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		$code                = $input->get('code', null, 'base64');
		$session             = $this->getContainer()->segment;
		$registrationRequest = $session->get('mfa_webauthn.publicKeyCredentialCreationOptions', null);

		// If there was no registration request BUT there is a registration response throw an error
		if (empty($registrationRequest) && !empty($code))
		{
			throw new RuntimeException($this->getLanguage()->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		// If there is no registration request (and there isn't a registration response) we are just saving the title.
		if (empty($registrationRequest))
		{
			return $defaultOptions;
		}

		// In any other case try to authorize the registration
		try
		{
			$user                      = $this->getContainer()->userManager->getUser();
			$repository                = new PasskeyRepositoryMFA($user->getId());
			$credentials               = new Credentials($repository, $this->getContainer()->logger, $this->getContainer(), $this->getLanguage());
			$publicKeyCredentialSource = $credentials->validateAuthenticationData($code);
		}
		catch (Exception $err)
		{
			throw new RuntimeException($err->getMessage(), 403);
		}
		finally
		{
			// Unset the request data from the session.
			$session->set('mfa_webauthn.publicKeyCredentialCreationOptions', null);
			$session->set('mfa_webauthn.registration_user_id', null);
		}

		// Return the configuration to be serialized
		return [
			'credentialId' => base64_encode(
				$publicKeyCredentialSource->getAttestedCredentialData()->getCredentialId()
			),
			'pubkeysource' => json_encode($publicKeyCredentialSource),
			'counter'      => 0,
		];
	}

	/**
	 * Returns the information used to render the captive MFA page.
	 *
	 * This is the page which appears right after you log in and asks you to validate your login with MFA.
	 *
	 * @param   Mfa  $record  The #__mfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onMfaCaptive(Mfa $record): array
	{
		// Make sure we are actually meant to handle this Method
		if ($record->method != self::METHOD_NAME)
		{
			return [];
		}

		/**
		 * The following code looks stupid. An explanation is in order.
		 *
		 * What we normally want to do is save the authentication data returned by getAuthenticateData into the session.
		 * This is what is sent to the authenticator through the Javascript API and signed. The signature is posted back
		 * to the form as the "code" which is read by onLoginGuardMfaValidate. That Method will read the authentication
		 * data from the session and pass it along with the key registration data (from the database) and the
		 * authentication response (the "code" submitted in the form) to the WebAuthn library for validation.
		 *
		 * Validation will work as long as the challenge recorded in the encrypted AUTHENTICATION RESPONSE matches, upon
		 * decryption, the challenge recorded in the AUTHENTICATION DATA.
		 *
		 * I observed that for whatever stupid reason the browser was sometimes sending TWO requests to the server's
		 * Captive login page but only rendered the FIRST. This meant that the authentication data sent to the key had
		 * already been overwritten in the session by the "invisible" second request. As a result the challenge would
		 * not match and we'd get a validation error.
		 *
		 * The code below will attempt to read the authentication data from the session first. If it exists it will NOT
		 * try to replace it (technically it replaces it with a copy of the same data - same difference!). If nothing
		 * exists in the session, however, it WILL store the (random seeded) result of the getAuthenticateData Method.
		 * Therefore the first request to the Captive login page will store a new set of authentication data whereas the
		 * second, "invisible", request will just reuse the same data as the first request, fixing the observed issue in
		 * a way that doesn't compromise security.
		 *
		 * In case you are wondering, yes, the data is removed from the session in the onLoginGuardMfaValidate Method.
		 * In fact it's the first thing we do after reading it, preventing constant reuse of the same set of challenges.
		 *
		 * That was fun to debug - for "poke your eyes with a rusty fork" values of fun.
		 */

		$session          = $this->getContainer()->segment;
		$pkOptionsEncoded = $session->get('mfa_webauthn.publicKeyCredentialRequestOptions', null);

		$force      = $this->getContainer()->input->getInt('force', 0);
		$html       = '';
		$htmlButton = '';

		try
		{
			if ($force)
			{
				throw new RuntimeException('Expected exception (good): force a new key request');
			}

			if (empty($pkOptionsEncoded))
			{
				throw new RuntimeException('Expected exception (good): we do not have a pending key request');
			}

			$serializedOptions = base64_decode((string) $pkOptionsEncoded);
			$pkOptions         = unserialize($serializedOptions);

			if (!is_object($pkOptions) || empty($pkOptions) ||
				!($pkOptions instanceof PublicKeyCredentialRequestOptions))
			{
				throw new RuntimeException('The pending key request is corrupt; a new one will be created');
			}

			$pkRequest = json_encode($pkOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		catch (Exception)
		{
			$user        = $this->getContainer()->userManager->getUser();
			$repository  = new PasskeyRepositoryMFA($user->getId());
			$credentials = new Credentials($repository, $this->getContainer()->logger, $this->getContainer(), $this->getLanguage());
			$pkRequest   = $credentials->createChallenge($record->user_id);
		}

		try
		{
			$document = $this->getContainer()->application->getDocument();
			$document->addScriptOptions('mfa.authData', base64_encode($pkRequest), false);

			$fakeView = new FakeView(
				$this->getContainer(), [
					'name' => 'Passkeymfa',
				]
			);

			$html       = $fakeView->loadAnyTemplate('Passkeysmfa/validate');
			$htmlButton = $fakeView->loadAnyTemplate('Passkeysmfa/validate_button');
		}
		catch (Exception)
		{
			return [];
		}

		$document = $this->getContainer()->application->getDocument();
		$document->addScript(
			Uri::base() . 'media/js/webauthn-mfa.min.js',
			defer: true
		);

		// Load JS translations
		$document->lang('PANOPTICON_MFA_PASSKEYS_ERR_NOTAVAILABLE_HEAD');
		$document->lang('PANOPTICON_MFA_PASSKEYS_ERR_NO_STORED_CREDENTIAL');

		$document->addScriptOptions('mfa.pagetype', 'validate', false);

		$helpURL = self::HELP_URL;

		return [
			'pre_message'        => $this->getLanguage()->text('PANOPTICON_MFA_PASSKEYS_LBL_INSTRUCTIONS'),
			'field_type'         => 'custom',
			'input_type'         => '',
			'placeholder'        => '',
			'label'              => '',
			'html'               => $html,
			'html_button'        => $htmlButton,
			'post_message'       => '',
			'hide_submit'        => true,
			'help_url'           => $helpURL,
			'allowEntryBatching' => true,
		];
	}

	/**
	 * Validates the code submitted by the user in the captive MFA page.
	 *
	 * If the record does not correspond to your plugin return FALSE.
	 *
	 * @param   Mfa          $record  The TFA method's record you're validatng against
	 * @param   User         $user    The user record
	 * @param   string|null  $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onMfaValidate(Mfa $record, User $user, ?string $code): bool
	{
		// Make sure we are actually meant to handle this Method
		if ($record->method != self::METHOD_NAME)
		{
			return false;
		}

		// Double-check the MFA Method is for the correct user
		if ($user->getId() != $record->user_id)
		{
			return false;
		}

		try
		{
			$user        = $this->getContainer()->userManager->getUser();
			$repository  = new PasskeyRepositoryMFA($user->getId());
			$credentials = new Credentials($repository, $this->getContainer()->logger, $this->getContainer(), $this->getLanguage());
			$credentials->validateChallenge($code);
		}
		catch (Exception $e)
		{
			try
			{
				$this->getContainer()->application->enqueueMessage($e->getMessage(), 'error');
			}
			catch (Exception)
			{
			}

			return false;
		}

		return true;
	}
}