<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Passkey\Authentication;
use Akeeba\Panopticon\Library\Passkey\CredentialRepository;
use Akeeba\Panopticon\Library\User\User;
use Awf\Container\Container;
use Awf\Mvc\Model;
use Awf\Uri\Uri;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;

/**
 * Manage passkeys used for logging the user in.
 *
 * @since  1.2.3
 */
final class Passkeys extends Model
{
	private Authentication $authenticationHelper;

	/** @inheritDoc */
	final public function __construct(?Container $container = null)
	{
		parent::__construct($container);

		$this->authenticationHelper = new Authentication(
			new CredentialRepository()
		);
	}

	/**
	 * Is logging in with passkey enabled?
	 *
	 * @return  bool
	 * @since   1.2.3
	 */
	final public function isEnabled(): bool
	{
		if (!$this->getContainer()->appConfig->get('passkey_login', true))
		{
			return false;
		}

		if (!function_exists('gmp_intval') && !function_exists('bccomp'))
		{
			return false;
		}

		return true;
	}

	final public function getDisplayVariables(?User $forUser): array
	{
		$ret = [
			'enabled'     => $this->isEnabled(),
			'user'        => null,
			'allow_add'   => false,
			'credentials' => [],
			'error'       => '',
			'showImages'  => true,
		];

		if (!$ret['enabled'])
		{
			return $ret;
		}

		$forUser            ??= $this->getContainer()->userManager->getUser();
		$ret['user']        = $this->getContainer()->userManager->getUser();
		$ret['allow_add']   = $forUser->getId() === $ret['user']->getId();
		$ret['credentials'] = (new CredentialRepository())->getAll($forUser->getId());

		return $ret;
	}

	/**
	 * Returns the Public Key Creation Options to start the attestation ceremony on the browser.
	 *
	 * This is step 1 of creating a new passkey.
	 *
	 * @param   bool  $resident  Is this a resident key?
	 *
	 * @return  PublicKeyCredentialCreationOptions
	 * @throws  Exception
	 * @since   1.2.3
	 */
	final public function getCreateOptions(bool $resident = false): PublicKeyCredentialCreationOptions
	{
		$session = $this->getContainer()->segment;
		$user    = $this->getContainer()->userManager->getUser();
		$session->set('panopticon.registration_user_id', $user->getId());

		return $this->authenticationHelper->getPubKeyCreationOptions($user, $resident);
	}

	/**
	 * Store a new passkey.
	 *
	 * This is step 2 of creating a new passkey.
	 *
	 * @param   string|null  $data  The raw data sent by the browser
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.2.3
	 */
	final public function save(?string $data): void
	{
		$session = $this->getContainer()->segment;
		$lang    = $this->getContainer()->language;

		$storedUserId = $session->get('panopticon.registration_user_id', 0);
		$thatUser     = empty($storedUserId)
			? $this->getContainer()->userManager->getUser()
			: $this->getContainer()->userManager->getUser($storedUserId);
		$myUser       = $this->getContainer()->userManager->getUser();

		if (!$thatUser->getId() || $thatUser->getId() != $myUser->getId())
		{
			// Unset the session variables used for registering authenticators (security precaution).
			$session->remove('panopticon.registration_user_id');
			$session->remove('panopticon.publicKeyCredentialCreationOptions');

			// Politely tell the presumed hacker trying to abuse this callback to go away.
			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_INVALID_USER'));
		}

		// Get the credentials repository object. It's outside the try-catch because I also need it to display the GUI.
		$credentialRepository = $this->authenticationHelper->getCredentialsRepository();

		try
		{
			// Try to validate the browser data. If there's an error I won't save anything and pass the message to the GUI.
			$publicKeyCredentialSource = $this->authenticationHelper->validateAttestationResponse($data);
		}
		finally
		{
			// Unset the session variables used for registering authenticators (security precaution).
			$session->remove('panopticon.registration_user_id');
			$session->remove('panopticon.publicKeyCredentialCreationOptions');

		}

		if (
			!is_object($publicKeyCredentialSource)
			|| !($publicKeyCredentialSource instanceof PublicKeyCredentialSource))
		{
			$publicKeyCredentialSource = null;

			throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREATE_NO_ATTESTED_DATA'));
		}

		$credentialRepository->saveCredentialSource($publicKeyCredentialSource);
	}

	/**
	 * Change the label of a passkey
	 *
	 * @param   string|null  $credentialId  The Credential ID of the passkey
	 * @param   string|null  $newLabel      The new label to set
	 *
	 * @return  bool  True on success
	 * @siince  1.2.3
	 */
	final public function saveLabel(?string $credentialId, ?string $newLabel): bool
	{
		$repository = $this->authenticationHelper->getCredentialsRepository();

		if (empty($credentialId))
		{
			return false;
		}

		$credentialId = base64_decode($credentialId);

		if (empty($credentialId) || !$repository->has($credentialId) || empty($newLabel))
		{
			return false;
		}

		// Make sure I am editing my own key
		try
		{
			$credentialHandle = $repository->getUserHandleFor($credentialId);
			$user             = $this->getContainer()->userManager->getUser();
			$myHandle         = $repository->getHandleFromUserId($user->getId());
		}
		catch (Exception $e)
		{
			return false;
		}

		if ($credentialHandle !== $myHandle)
		{
			return false;
		}

		// Save the new label
		try
		{
			$repository->setLabel($credentialId, $newLabel);

			return true;
		}
		catch (Exception)
		{
			return false;
		}
	}

	/**
	 * Remove a passkey
	 *
	 * @param   string  $credentialId  The Credential ID of the passkey to remove
	 *
	 * @return  bool
	 * @since   1.2.3
	 */
	final public function delete(string $credentialId): bool
	{
		$session = $this->getContainer()->segment;
		$lang    = $this->getContainer()->language;

		$repository = $this->authenticationHelper->getCredentialsRepository();

		// Is this a valid credential?
		if (empty($credentialId))
		{
			return false;
		}

		$credentialId = base64_decode($credentialId);

		if (empty($credentialId) || !$repository->has($credentialId))
		{
			return false;
		}

		// Make sure I am editing my own key
		try
		{
			$user             = $this->getContainer()->userManager->getUser();
			$credentialHandle = $repository->getUserHandleFor($credentialId);
			$myHandle         = $repository->getHandleFromUserId($user->getId());
		}
		catch (Exception)
		{
			return false;
		}

		if ($credentialHandle !== $myHandle)
		{
			return false;
		}

		// Delete the record
		try
		{
			$repository->remove($credentialId);
		}
		catch (Exception)
		{
			return false;
		}

		return true;
	}

	/**
	 * Returns the JSON-encoded Public Key Credential Request.
	 *
	 * This is step 1 of logging in with a passkey.
	 *
	 * @param   string|null  $username
	 * @param   string|null  $returnUrl
	 *
	 * @return  string A JSON-encoded object or JSON-encoded false (invalid username, no credentials)
	 * @throws  Exception
	 * @since   1.2.3
	 */
	final public function challenge(?string $username, ?string $returnUrl): string
	{
		$session     = $this->getContainer()->segment;
		$userManager = $this->getContainer()->userManager;

		// Retrieve data from the request
		$returnUrl ??= base64_encode($session->get('panopticon.returnUrl', Uri::current()));
		$returnUrl = base64_decode($returnUrl);

		// For security reasons the post-login redirection URL must be internal to the site.
		if (!Uri::isInternal($returnUrl))
		{
			// If the URL wasn't an internal redirect to the site's root.
			$returnUrl = Uri::base();
		}

		// Get the return URL
		$session->set('panopticon.returnUrl', $returnUrl);

		// Is the username valid?
		$userId = empty($username) ? 0 : $userManager->getUserByUsername($username);
		$myUser = $userManager->getUser($userId);

		$effectiveUser                     = $userId === 0 ? null : $myUser;
		$publicKeyCredentialRequestOptions = $this->authenticationHelper
			->getPubkeyRequestOptions($effectiveUser);

		$session->set('panopticon.userId', $userId);

		// Return the JSON encoded data to the caller
		return json_encode($publicKeyCredentialRequestOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Validate the authenticator response.
	 *
	 * This is step 2 of logging in with a passkey.
	 *
	 * @param   string|null  $data  The raw authentication response data sent by the browser
	 *
	 * @return  void
	 * @since   1.2.3
	 */
	public function login(?string $data): void
	{
		$session     = $this->getContainer()->segment;
		$userManager = $this->getContainer()->userManager;
		$lang        = $this->getContainer()->language;

		$returnUrl = $session->get('panopticon.returnUrl', Uri::base());
		$userId    = $session->get('panopticon.userId', 0);

		$redirectMessage = '';
		$redirectType    = 'info';

		try
		{
			if (!$this->isEnabled())
			{
				throw new RuntimeException('PANOPTICON_PASSKEYS_ERR_DISABLED');
			}

			// Validate the authenticator response and get the user handle
			$credentialRepository = $this->authenticationHelper->getCredentialsRepository();

			// Login Flow 1: Login with a non-resident key
			if (!empty($userId))
			{
				// Make sure the user exists
				$user = $userManager->getUser($userId);

				if ($user->getId() != $userId)
				{
					throw new RuntimeException($lang->text('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
				}

				// Validate the authenticator response and get the user handle
				$userHandle = $this->getUserHandleFromResponse($user, $data);

				if (is_null($userHandle))
				{
					throw new RuntimeException($lang->text('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
				}

				// Does the user handle match the user ID? This should never trigger by definition of the login check.
				$validUserHandle = $credentialRepository->getHandleFromUserId($userId);

				if ($userHandle != $validUserHandle)
				{
					throw new RuntimeException($lang->text('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
				}

				if ($user->getId() != $userId)
				{
					throw new RuntimeException($lang->text('PANOPTICON_MFA_PASSKEYS_ERR_CREATE_INVALID_LOGIN_REQUEST'));
				}

				// Login the user
				$this->loginUser((int) $userId);

				return;
			}

			// Login Flow 2: Login with a resident key
			$userHandle = $this->getUserHandleFromResponse(null, $data);

			if (is_null($userHandle))
			{
				throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_EMPTY_USERNAME'));
			}

			// Get the user ID from the user handle
			$repo   = $this->authenticationHelper->getCredentialsRepository();
			$userId = $repo->getUserIdFromHandle($userHandle);

			// If the user was not found show an error
			if ($userId <= 0)
			{
				throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_INVALID_USERNAME_RESIDENT'));
			}

			// Login the user
			$this->loginUser((int) $userId);
		}
		catch (\Throwable $e)
		{
			$session->set('panopticon.publicKeyCredentialRequestOptions', null);

			$redirectMessage = $e->getMessage();
			$redirectType    = 'error';

			$this->processLoginFailure($e->getMessage());
		}
		finally
		{
			/**
			 * This code needs to run no matter if the login succeeded or failed. It prevents replay attacks and takes
			 * the user back to the page they started from.
			 */

			// Remove temporary information for security reasons
			$session->set('panopticon.publicKeyCredentialRequestOptions', null);
			$session->set('panopticon.userHandle', null);
			$session->set('panopticon.returnUrl', null);
			$session->set('panopticon.userId', null);

			// Redirect back to the page we were before.
			$this->getContainer()->application->redirect($returnUrl, $redirectMessage, $redirectType);
		}
	}

	/**
	 * Validate the authenticator response sent to us by the browser.
	 *
	 * @return  string|null  The user handle or null
	 * @throws  Exception
	 * @since   1.2.3
	 */
	private function getUserHandleFromResponse(?User $user, ?string $data): ?string
	{
		// Retrieve data from the request and session
		$pubKeyCredentialSource = $this->authenticationHelper
			->validateAssertionResponse(
				$data,
				$user
			);

		return $pubKeyCredentialSource ? $pubKeyCredentialSource->getUserHandle() : null;
	}

	/**
	 * Logs the user into the application.
	 *
	 * @param   int  $userId  The user ID to log in.
	 *
	 * @return  void
	 * @since   1.2.3
	 */
	private function loginUser(int $userId): void
	{
		/** @var LoggerInterface $logger */
		$logger = $this->container->loggerFactory->get('login');
		/** @var Loginfailures $loginFailureModel */
		$loginFailureModel = $this->getContainer()->mvcFactory->makeModel('Loginfailures');
		$userManager       = $this->getContainer()->userManager;
		$user              = $userManager->getUser($userId);

		$logger->info('Successful passkey login', ['username' => $user->getUsername()]);

		// This clears the internal variables in the user manager object
		$userManager->logoutUser();

		$this->container->segment->set('user_id', $userId);
		$this->container->session->regenerateId();

		$loginFailureModel->cleanupOldFailures();

		// Disable MFA if we're told to do so for passkey logins
		if ($this->getContainer()->appConfig->get('passkey_login_no_mfa', true))
		{
			$this->getContainer()->segment->set('panopticon.mfa_checked', true);
		}
	}

	/**
	 * Record a login failure
	 *
	 * @param   string|null  $errorMessage  The error message to log
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.2.3
	 */
	private function processLoginFailure(?string $errorMessage): void
	{
		/** @var LoggerInterface $logger */
		$logger = $this->container->loggerFactory->get('login');
		/** @var Loginfailures $loginFailureModel */
		$loginFailureModel = $this->getContainer()->mvcFactory->makeModel('Loginfailures');
		$userManager       = $this->getContainer()->userManager;

		$userManager->logoutUser();

		$this->getContainer()->segment->remove('panopticon.mfa_checked');

		$logger->error(
			'Failed passkey login', [
				'error' => $errorMessage,
			]
		);

		$loginFailureModel->logFailure(true);
	}
}