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
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

/**
 * Manage passkeys used for logging the user in.
 *
 * @since  1.2.3
 */
final class Passkeys extends Model
{
	private Authentication $authenticationHelper;

	private LoggerInterface $logger;

	/** @inheritDoc */
	final public function __construct(?Container $container = null)
	{
		parent::__construct($container);

		$this->logger               = $this->getContainer()->loggerFactory->get('passkey');
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
			'enabled'         => $this->isEnabled(),
			'user'            => null,
			'allow_add'       => false,
			'credentials'     => [],
			'error'           => '',
			'showImages'      => true,
			'user_decides_pw' => false,
		];

		if (!$ret['enabled'])
		{
			return $ret;
		}

		$forUser                ??= $this->getContainer()->userManager->getUser();
		$ret['user']            = $this->getContainer()->userManager->getUser();
		$ret['allow_add']       = $forUser->getId() === $ret['user']->getId();
		$ret['credentials']     = (new CredentialRepository())->getAll($forUser->getId());
		$ret['user_decides_pw'] = $this->getContainer()->appConfig->get('passkey_login_no_password', 'user') === 'user';

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
		$this->logger->debug(
			sprintf(
				'Generating the Public Key Creation Options for a new %s authenticator attestation',
				$resident ? 'resident' : 'roaming'
			)
		);

		$session = $this->getContainer()->segment;
		$user    = $this->getContainer()->userManager->getUser();
		$session->set('passkey.registration_user_id', $user->getId());

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
		$this->logger->debug('Performing attestation of a new authenticator');

		$session = $this->getContainer()->segment;
		$lang    = $this->getContainer()->language;

		$storedUserId = $session->get('passkey.registration_user_id', 0);
		$thatUser     = empty($storedUserId)
			? $this->getContainer()->userManager->getUser()
			: $this->getContainer()->userManager->getUser($storedUserId);
		$myUser       = $this->getContainer()->userManager->getUser();

		if (!$thatUser->getId() || $thatUser->getId() != $myUser->getId())
		{
			$this->logger->notice(
				sprintf(
					'Attestation failed. Requested for user ID %d, current user ID %d and found user ID %d.',
					$storedUserId,
					$myUser->getId(),
					$thatUser->getId()
				)
			);

			// Unset the session variables used for registering authenticators (security precaution).
			$session->remove('passkey.registration_user_id');
			$session->remove('passkey.publicKeyCredentialCreationOptions');

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
		catch (\Throwable $e)
		{
			$this->debugLogThrowable($e);

			throw $e;
		}
		finally
		{
			// Unset the session variables used for registering authenticators (security precaution).
			$session->remove('passkey.registration_user_id');
			$session->remove('passkey.publicKeyCredentialCreationOptions');
		}

		if (
			!is_object($publicKeyCredentialSource)
			|| !($publicKeyCredentialSource instanceof PublicKeyCredentialSource))
		{
			$this->logger->notice(
				'No attested data. The authenticator has not been added.'
			);

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
		$repository   = $this->authenticationHelper->getCredentialsRepository();
		$credentialId = @base64_decode($credentialId);

		if (empty($credentialId))
		{
			$this->logger->error('Change label failed. No credential ID given.');

			return false;
		}

		if (empty($credentialId) || !$repository->has($credentialId))
		{
			$this->logger->error(
				sprintf(
					'Change label failed. Invalid credential ID "%s" given.',
					$credentialId
				)
			);

			return false;
		}

		if (empty($newLabel))
		{
			$this->logger->error('Change label failed. No new label given.');

			return false;
		}


		// Make sure I am editing my own key
		try
		{
			$credentialHandle = $repository->getUserHandleFor($credentialId);
			$user             = $this->getContainer()->userManager->getUser();
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
		$repository   = $this->authenticationHelper->getCredentialsRepository();
		$credentialId = @base64_decode($credentialId);

		// Is this a valid credential?
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
	final public function challenge(?string $returnUrl = null): PublicKeyCredentialRequestOptions
	{
		$this->logger->info('Creating a passkey login challenge');

		$session = $this->getContainer()->segment;

		// Retrieve data from the request
		$returnUrl ??= base64_encode($session->get('passkey.returnUrl', Uri::current()));
		$returnUrl = base64_decode($returnUrl);

		// For security reasons the post-login redirection URL must be internal to the site.
		if (!Uri::isInternal($returnUrl))
		{
			// If the URL wasn't an internal redirect to the site's root.
			$returnUrl = Uri::base();
		}

		// Get the return URL
		$session->set('passkey.returnUrl', $returnUrl);

		// Is the username valid?
		return $this->authenticationHelper->getPubkeyRequestOptions();
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
		$session = $this->getContainer()->segment;
		$lang    = $this->getContainer()->language;

		$returnUrl = $session->get('passkey.returnUrl', Uri::base());

		$redirectMessage = '';
		$redirectType    = 'info';

		try
		{
			if (!$this->isEnabled())
			{
				throw new RuntimeException('PANOPTICON_PASSKEYS_ERR_DISABLED');
			}

			$userHandle = $this->getUserHandleFromResponse($data);

			if (is_null($userHandle))
			{
				throw new RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_EMPTY_USERNAME'));
			}

			// Get the user ID from the user handle
			$userId = $this->authenticationHelper->getCredentialsRepository()->getUserIdFromHandle($userHandle);

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
			echo "<pre>";
			echo $e->getMessage() . "\n";
			echo $e->getFile() . ':' . $e->getLine() . "\n";
			echo $e->getTraceAsString();
			die('derp');

			$session->set('passkey.publicKeyCredentialRequestOptions', null);

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
			$session->set('passkey.publicKeyCredentialRequestOptions', null);
			$session->set('passkey.userHandle', null);
			$session->set('passkey.returnUrl', null);

			// Redirect back to the page we were before.
			$this->getContainer()->application->redirect($returnUrl, $redirectMessage, $redirectType);
		}
	}

	/**
	 * Retrieve the Authentication helper instance
	 *
	 * @return  Authentication
	 * @since   1.2.3
	 */
	public function getAuthenticationHelper(): Authentication
	{
		return $this->authenticationHelper;
	}

	/**
	 * Validate the authenticator response sent to us by the browser.
	 *
	 * @return  string|null  The user handle or null
	 * @throws  Exception
	 * @since   1.2.3
	 */
	private function getUserHandleFromResponse(?string $data): ?string
	{
		// Retrieve data from the request and session
		return $this->authenticationHelper
			->validateAssertionResponse($data)
			->getUserHandle();
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

	private function debugLogThrowable(\Throwable $e)
	{
		$this->logger->info(str_repeat('-', 10) . ' EXCEPTION ' . str_repeat('-', 10));
		$this->logger->error($e->getMessage());
		$this->logger->debug($e->getFile() . ':' . $e->getLine());

		foreach (explode("\n", $e->getTraceAsString()) as $line)
		{
			$this->logger->debug($line);
		}

		if ($previous = $e->getPrevious())
		{
			$this->logger->info('Related to previous exception:');
			$this->debugLogThrowable($previous);
		}
		else
		{
			$this->logger->info(str_repeat('-', 40));
		}
	}
}