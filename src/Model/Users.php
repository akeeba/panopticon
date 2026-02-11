<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Helper\ForbiddenUsernames;
use Akeeba\Panopticon\Library\MultiFactorAuth\MFATrait;
use Akeeba\Panopticon\Library\Passkey\PasskeyTrait;
use Akeeba\Panopticon\Model\Trait\UserAvatarTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Awf\Container\Container;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Uri\Uri;
use Awf\User\User;
use Awf\User\UserInterface;
use RuntimeException;

/**
 * User Management Model
 *
 * @property int    $id
 * @property string $username
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $parameters
 *
 * @since  1.0.0
 */
class Users extends DataModel
{
	use UserAvatarTrait;
	use EmailSendingTrait;
	use PasskeyTrait;
	use MFATrait;

	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__users';
		$this->idFieldName = 'id';

		parent::__construct($container);

		//$this->addBehaviour('filters');
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$search = trim($this->getState('search', null) ?? '');

		if (!empty($search))
		{
			$query->extendWhere(
				'AND', [
				$query->quoteName('username') . ' LIKE ' . $query->quote('%' . $search . '%'),
				$query->quoteName('name') . ' LIKE ' . $query->quote('%' . $search . '%'),
				$query->quoteName('email') . ' LIKE ' . $query->quote('%' . $search . '%'),
			], 'OR'
			);
		}

		return $query;
	}

	public function getGroupsForSelect(): array
	{
		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('title'),
				]
			)
			->from($db->quoteName('#__groups'));

		return array_map(fn($x) => $x->title, $db->setQuery($query)->loadObjectList('id') ?: []);
	}

	/**
	 * Submit a password reset request
	 *
	 * @param   string  $username  The username of the user to reset the password for.
	 * @param   string  $email     The email of the user to reset the password for.
	 *
	 * @return  void
	 */
	public function createPasswordResetRequest(string $username, string $email)
	{
		$appConfig   = Factory::getContainer()->appConfig;
		$lang        = Factory::getContainer()->language;
		$userManager = Factory::getContainer()->userManager;

		// Are password resets even enabled?
		if (!$appConfig->get('pwreset', true))
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_PWRESET_DISABLED'));
		}

		// Try to locate the user
		$user = $userManager->getUserByUsername($username);

		if (empty($user) || !$user->getId())
		{
			// No user? Return without an error to prevent any kind of information leak.
			return;
		}

		// Is the user's email address the one we have on file?
		$email = strtolower(trim($email));

		if ($email !== strtolower(trim($user->getEmail())))
		{
			// Email does not match? Return without an error to prevent any kind of information leak.
			return;
		}

		// Check if the user is allowed to reset their password.
		if (!$this->canResetPassword($user))
		{
			return;
		}

		// Check minimum time between password resets
		$lastReset = $user->getParameters()->get('pwreset.timestamp', null);
		$minTime   = $appConfig->get('pwreset_mintime', 300);

		if (!empty($lastReset) && is_numeric($lastReset) && $minTime > 0 && time() - intval($lastReset) < $minTime)
		{
			return;
		}

		// Check maximum number of password reset attempts
		$resetCount = $user->getParameters()->get('pwreset.count', 0);
		$maxCount   = $appConfig->get('pwreset_maxfails', 3);

		if ($resetCount >= $maxCount)
		{
			return;
		}

		// Save the information about the password reset.
		$pwResetSecret = random_bytes(64);
		$user->getParameters()->set('pwreset.secret', base64_encode($pwResetSecret));
		$user->getParameters()->set('pwreset.timestamp', time());
		$userManager->saveUser($user);

		// Send the email.
		$data = new Registry();
		$data->set('template', 'pwreset');
		$data->set(
			'email_variables', [
				'NAME'     => $user->getName(),
				'USERNAME' => $user->getUsername(),
				'EMAIL'    => $user->getEmail(),
				'USERID'   => $user->getId(),
				'TOKEN'    => hash_hmac(
					'sha1',
					implode(':', [$user->getEmail(), $user->getUsername(), $user->getPassword()]),
					$pwResetSecret
				),
			]
		);
		$data->set('recipient_id', $user->getId());

		$logger = Factory::getContainer()->loggerFactory->get('login');
		$logger->debug('Sending email pwreset: password reset', $data->toArray());

		$this->enqueueEmail($data, null);
	}

	/**
	 * Can the user request a password reset?
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 * @since   1.3.0
	 */
	public function canResetPassword(UserInterface $user): bool
	{
		$appConfig = Factory::getContainer()->appConfig;

		// Do I have a valid user?
		if (!$user->getId())
		{
			return false;
		}

		// Are password resets even enabled?
		if (!$appConfig->get('pwreset', true))
		{
			return false;
		}

		// Check superuser restriction
		if (!$appConfig->get('pwreset_superuser', false) && $user->getPrivilege('panopticon.super', false))
		{
			return false;
		}

		// Check administrator restriction
		if (!$appConfig->get('pwreset_admin', false) && $user->getPrivilege('panopticon.admin', false))
		{
			return false;
		}

		// Check user groups
		$allowedGroups = $appConfig->get('pwreset_groups', []);
		$allowedGroups = is_array($allowedGroups) ? array_values($allowedGroups) : [];
		$userGroups    = $user->getParameters()->get('usergroups', []);
		$userGroups    = is_array($userGroups) ? array_values($userGroups) : [];
		$intersection  = array_intersect($allowedGroups, $userGroups);
		$isAllowed     = empty($allowedGroups)
		                 || (!empty($allowedGroups) && !empty($userGroups)
		                     && !empty($intersection));

		if (!$isAllowed)
		{
			return false;
		}

		return true;
	}

	/**
	 * Perform a password reset.
	 *
	 * After performing a number of security and sanity checks, this method will reset the user's password, and
	 * optionally remove passkeys and/or Multi-factor Authentication Methods.
	 *
	 * If passkeys-only login is enabled globally, for the user group, or the user account: the existing passkeys
	 * will not be removed from the user account. This would be insecure, making access to the user's email have
	 * priority against the much more secure passkey authentication method. This kind of account will require the
	 * intervention of a Superuser to remove all passkeys. Then, the user can use their (reset) password to log into
	 * Panopticon, where they will be required to set up their first and mandatory passkey.
	 *
	 * If forced MFA is enabled globally, or for the user group: the existing MFA methods will not be removed from the
	 * user account. This would be insecure, as access to the user's email would effectively bypass MFA altogether. MFA
	 * is designed to protect against this kind of scenario. This kind of account will require the intervention of a
	 * Superuser to disable MFA.
	 *
	 * Forced MFA users
	 *
	 * @param   UserInterface  $user      The user to reset the password on.
	 * @param   string         $token     The Token provided by the user.
	 * @param   string         $password  The new password.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function passwordReset(UserInterface $user, string $token, string $password)
	{
		if (!$user->getId())
		{
			throw new RuntimeException('No user provided.');
		}

		if (empty($token))
		{
			throw new RuntimeException('No token provided.');
		}

		if (empty($password) || empty(trim($password)))
		{
			throw new RuntimeException(
				'No password provided, or password consists entirely of whitespace (forbidden).'
			);
		}

		if (!$this->canResetPassword($user))
		{
			throw new RuntimeException('The user is not allowed to reset their password at this time.');
		}

		$timestamp            = $user->getParameters()->get('pwreset.timestamp', 0);
		$pwResetSecretEncoded = $user->getParameters()->get('pwreset.secret', '');

		if (empty($timestamp) || empty($pwResetSecretEncoded))
		{
			throw new RuntimeException('The user had not requested a password reset.');
		}

		if ($timestamp > time())
		{
			throw new RuntimeException('Password reset request is in the future.');
		}

		try
		{
			$pwResetSecret = @base64_decode((string) $pwResetSecretEncoded);
		}
		catch (\Exception)
		{
			$pwResetSecret = '';
		}

		if (empty($pwResetSecret))
		{
			throw new RuntimeException('The password reset secret is empty or invalid.');
		}

		$canonicalToken = hash_hmac(
			'sha1',
			implode(':', [$user->getEmail(), $user->getUsername(), $user->getPassword()]),
			$pwResetSecret
		);

		if (!hash_equals($canonicalToken, $token))
		{
			throw new RuntimeException('The password reset token is invalid.');
		}

		// Reset password
		$user->setPassword($password);
		$user->getParameters()->set('pwreset.timestamp', null);
		$user->getParameters()->set('pwreset.secret', null);
		$user->getParameters()->set('pwreset.count', null);
		Factory::getContainer()->userManager->saveUser($user);

		$appConfig = Factory::getContainer()->appConfig;
		$logger = Factory::getContainer()->loggerFactory->get('login');

		// Optional: reset passkeys BUT NOT for passkey-only login users
		$resetPasskeys  = $appConfig->get('pwreset_passkeys', false);
		$forcedPasskeys = $this->isForcedPasskeyLoginEnabled($user);

		if ($resetPasskeys && !$forcedPasskeys)
		{
			// Remove passkeys
			/** @var Passkeys $passkeysModel */
			$passkeysModel = $this->container->mvcFactory->makeTempModel('Passkeys');
			$vars          = $passkeysModel->getDisplayVariables($user);

			foreach ($vars['credentials'] as $method)
			{
				$passkeysModel->delete($method['id']);
			}
		}
		elseif ($resetPasskeys)
		{
			$logger->notice('Will not reset passkeys: the user is forced to use passkey-only login.');
		}

		// Optional: reset MFA BUT NOT for forced-MFA users
		$resetMFA  = $appConfig->get('pwreset_mfa', false);
		$forcedMFA = $this->isForcedMFAEnabled($user);

		if ($resetMFA && !$forcedMFA)
		{
			/** @var Mfamethods $mfaMethodsModel */
			$mfaMethodsModel = Factory::getContainer()->mvcFactory->makeTempModel('Mfamethods');
			$mfaMethodsModel->deleteAll($user);
		}
		elseif ($resetMFA)
		{
			$logger->notice('Will not reset MFA: the user is forced to use MFA.');
		}
	}

	/**
	 * Create a new user registration.
	 *
	 * @param   string  $username  The desired username
	 * @param   string  $email     The email address
	 * @param   string  $password  The password
	 * @param   string  $name      The full name
	 *
	 * @return  User  The created user object
	 */
	public function createRegistration(string $username, string $email, string $password, string $name): User
	{
		$container   = Factory::getContainer();
		$appConfig   = $container->appConfig;
		$userManager = $container->userManager;
		$lang        = $container->language;

		$registrationType = $appConfig->get('user_registration', 'disabled');

		if (!in_array($registrationType, ['admin', 'self'], true))
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_REGISTRATION_DISABLED'));
		}

		// Validate username
		$username = trim($username);

		if (empty($username))
		{
			throw new RuntimeException($lang->text('PANOPTICON_SETUP_ERR_USER_EMPTYUSERNAME'));
		}

		// Check forbidden usernames
		if (ForbiddenUsernames::isForbidden($username, $container))
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_FORBIDDEN_USERNAME'));
		}

		// Check username uniqueness
		if ($userManager->getUserByUsername($username) !== null)
		{
			throw new RuntimeException(
				$lang->sprintf('PANOPTICON_USERS_ERR_USERNAME_EXISTS', htmlentities($username))
			);
		}

		// Validate email
		$email = strtolower(trim($email));

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
		{
			throw new RuntimeException(
				$lang->sprintf('PANOPTICON_USERS_ERR_INVALID_EMAIL', htmlentities($email))
			);
		}

		// Check email domain
		$this->validateEmailDomain($email);

		// Check email uniqueness
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));

		if ((int) $db->setQuery($query)->loadResult() > 0)
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_EMAIL_EXISTS'));
		}

		// Validate name
		$name = trim($name);

		if (empty($name))
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_EMPTY_NAME'));
		}

		// Validate password
		if (empty($password))
		{
			throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_NEEDS_PASSWORD'));
		}

		// Create the user (blocked by default)
		$user = new User();
		$user->setUsername($username);
		$user->setName($name);
		$user->setEmail($email);
		$user->setPassword($password);

		// Set registration parameters
		$user->getParameters()->set('registration.created', time());
		$user->getParameters()->set('registration.type', $registrationType);
		$user->getParameters()->set('registration.activation_tries', 0);

		// Assign to default group
		$defaultGroup = (int) $appConfig->get('user_registration_default_group', 0);

		if ($defaultGroup > 0)
		{
			$user->getParameters()->set('usergroups', [$defaultGroup]);
		}

		// Block the user (pending activation or admin approval)
		$user->getParameters()->set('block', true);

		$userManager->saveUser($user);

		// Send appropriate email
		if ($registrationType === 'admin')
		{
			$this->sendRegistrationPendingAdminEmail($user);
		}
		elseif ($registrationType === 'self')
		{
			$this->sendRegistrationActivateEmail($user);
		}

		return $user;
	}

	/**
	 * Validate the activation token for a self-registration.
	 *
	 * @param   User    $user      The user to activate
	 * @param   string  $username  The submitted username
	 * @param   string  $password  The submitted password
	 * @param   string  $token     The submitted activation token
	 *
	 * @return  bool  True if the token is valid
	 */
	public function validateActivationToken(User $user, string $username, string $password, string $token): bool
	{
		$container = Factory::getContainer();
		$appConfig = $container->appConfig;

		// Check registration type
		$regType = $user->getParameters()->get('registration.type', null);

		if ($regType !== 'self')
		{
			return false;
		}

		// Check if there's a stored secret
		$secretEncoded = $user->getParameters()->get('registration.secret', '');

		if (empty($secretEncoded))
		{
			return false;
		}

		// Check activation tries
		$maxTries    = (int) $appConfig->get('user_registration_activation_tries', 3);
		$currentTries = (int) $user->getParameters()->get('registration.activation_tries', 0);

		if ($currentTries >= $maxTries)
		{
			return false;
		}

		// Check activation time
		$maxDays     = (int) $appConfig->get('user_registration_activation_days', 7);
		$createdTime = (int) $user->getParameters()->get('registration.created', 0);
		$maxTime     = $createdTime + ($maxDays * 86400);

		if (time() > $maxTime)
		{
			return false;
		}

		// Verify username matches
		if ($user->getUsername() !== $username)
		{
			return false;
		}

		// Verify password
		if (!password_verify($password, $user->getPassword()))
		{
			return false;
		}

		// Verify the HMAC token
		try
		{
			$secret = base64_decode($secretEncoded);
		}
		catch (\Throwable)
		{
			return false;
		}

		$expectedToken = hash_hmac(
			'sha1',
			implode(':', [$user->getUsername(), $user->getEmail(), $user->getPassword()]),
			$secret
		);

		return hash_equals($expectedToken, $token);
	}

	/**
	 * Activate a registered user account.
	 *
	 * @param   User  $user  The user to activate
	 *
	 * @return  void
	 */
	public function activateRegistration(User $user): void
	{
		$container = Factory::getContainer();

		// Unblock user
		$user->getParameters()->set('block', false);

		// Clear registration parameters
		$user->getParameters()->set('registration.created', null);
		$user->getParameters()->set('registration.type', null);
		$user->getParameters()->set('registration.activation_tries', null);
		$user->getParameters()->set('registration.secret', null);

		$container->userManager->saveUser($user);

		// Send approval email
		$this->sendRegistrationApprovedEmail($user);
	}

	/**
	 * Clean up stale registrations that have exceeded their activation period.
	 *
	 * @return  int  The number of deleted stale registrations
	 */
	public function cleanupStaleRegistrations(): int
	{
		$container = Factory::getContainer();
		$appConfig = $container->appConfig;
		$maxDays   = (int) $appConfig->get('user_registration_activation_days', 7);
		$maxTime   = time() - ($maxDays * 86400);
		$deleted   = 0;

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('parameters'),
			])
			->from($db->quoteName('#__users'));

		// Find users with registration.created parameter
		$query->where(
			$query->jsonExtract($db->quoteName('parameters'), '$.registration.created') . ' IS NOT NULL'
		);
		$query->where(
			$query->jsonExtract($db->quoteName('parameters'), '$.registration.created') . ' > 0'
		);

		$users = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($users as $userRecord)
		{
			try
			{
				$params = new Registry($userRecord->parameters);
				$created = (int) $params->get('registration.created', 0);

				if ($created > 0 && $created < $maxTime)
				{
					$user = $container->userManager->getUser($userRecord->id);

					// Send expired notification
					$this->sendRegistrationExpiredEmail($user);

					// Delete the user
					$deleteQuery = $db->getQuery(true)
						->delete($db->quoteName('#__users'))
						->where($db->quoteName('id') . ' = ' . (int) $userRecord->id);

					$db->setQuery($deleteQuery)->execute();

					$deleted++;
				}
			}
			catch (\Throwable)
			{
				// Silently continue
			}
		}

		return $deleted;
	}

	/**
	 * Check if a user was just unblocked by an admin (for registration approval flow).
	 *
	 * Should be called after saving a user who was previously blocked.
	 *
	 * @param   User   $user            The user being saved
	 * @param   bool   $wasBlocked      Whether the user was previously blocked
	 * @param   bool   $isNowBlocked    Whether the user is now blocked
	 *
	 * @return  void
	 */
	public function handleAdminApproval(User $user, bool $wasBlocked, bool $isNowBlocked): void
	{
		if (!$wasBlocked || $isNowBlocked)
		{
			return;
		}

		$regType = $user->getParameters()->get('registration.type', null);

		if ($regType !== 'admin')
		{
			return;
		}

		// Clear registration parameters
		$user->getParameters()->set('registration.created', null);
		$user->getParameters()->set('registration.type', null);
		$user->getParameters()->set('registration.activation_tries', null);

		Factory::getContainer()->userManager->saveUser($user);

		// Send approval email
		$this->sendRegistrationApprovedEmail($user);
	}

	/**
	 * Send an expiration email and delete the user.
	 *
	 * @param   User  $user  The user to expire
	 *
	 * @return  void
	 */
	public function sendExpiredAndDelete(User $user): void
	{
		$this->sendRegistrationExpiredEmail($user);

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__users'))
			->where($db->quoteName('id') . ' = ' . (int) $user->getId());

		$db->setQuery($query)->execute();
	}

	/**
	 * Validate an email address domain against allowed/disallowed lists.
	 *
	 * @param   string  $email  The email to validate
	 *
	 * @return  void
	 * @throws  RuntimeException  If the domain is not allowed
	 */
	private function validateEmailDomain(string $email): void
	{
		$container = Factory::getContainer();
		$appConfig = $container->appConfig;
		$lang      = $container->language;

		$domain = strtolower(substr($email, strrpos($email, '@') + 1));

		// Check allowed domains (if configured)
		$allowedDomains = trim((string) $appConfig->get('user_registration_allowed_domains', ''));

		if (!empty($allowedDomains))
		{
			$allowed = array_map(
				fn($line) => strtolower(trim($line)),
				preg_split('/[\s,]+/', $allowedDomains, -1, PREG_SPLIT_NO_EMPTY)
			);

			if (!empty($allowed) && !in_array($domain, $allowed, true))
			{
				throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_EMAIL_DOMAIN_NOT_ALLOWED'));
			}
		}

		// Check disallowed domains
		$disallowedDomains = trim((string) $appConfig->get('user_registration_disallowed_domains', ''));

		if (!empty($disallowedDomains))
		{
			$disallowed = array_map(
				fn($line) => strtolower(trim($line)),
				preg_split('/[\s,]+/', $disallowedDomains, -1, PREG_SPLIT_NO_EMPTY)
			);

			if (in_array($domain, $disallowed, true))
			{
				throw new RuntimeException($lang->text('PANOPTICON_USERS_ERR_EMAIL_DOMAIN_NOT_ALLOWED'));
			}
		}
	}

	/**
	 * Send the "registration pending admin approval" email to the user.
	 *
	 * @param   User  $user  The registered user
	 *
	 * @return  void
	 */
	private function sendRegistrationPendingAdminEmail(User $user): void
	{
		$container = Factory::getContainer();
		$data      = new Registry();

		$data->set('template', 'registration_pending_admin');
		$data->set('email_variables', [
			'NAME'     => $user->getName(),
			'USERNAME' => $user->getUsername(),
			'EMAIL'    => $user->getEmail(),
			'SITENAME' => $container->appConfig->get('fromname', 'Panopticon'),
			'SITEURL'  => Uri::base(),
		]);
		$data->set('recipient_id', $user->getId());

		$this->enqueueEmail($data, null);
	}

	/**
	 * Send the "activate your account" email to the user (self-approval mode).
	 *
	 * @param   User  $user  The registered user
	 *
	 * @return  void
	 */
	private function sendRegistrationActivateEmail(User $user): void
	{
		$container = Factory::getContainer();
		$appConfig = $container->appConfig;

		// Generate activation secret and token
		$secret = random_bytes(64);
		$token  = hash_hmac(
			'sha1',
			implode(':', [$user->getUsername(), $user->getEmail(), $user->getPassword()]),
			$secret
		);

		// Store the secret on the user
		$user->getParameters()->set('registration.secret', base64_encode($secret));
		$container->userManager->saveUser($user);

		// Build the activation URL
		$activationUrl = Uri::base() . $container->router->route(
			sprintf(
				'index.php?view=users&task=activate&id=%d&token=%s',
				$user->getId(),
				$token
			)
		);

		$data = new Registry();
		$data->set('template', 'registration_activate');
		$data->set('email_variables', [
			'NAME'           => $user->getName(),
			'USERNAME'       => $user->getUsername(),
			'EMAIL'          => $user->getEmail(),
			'ACTIVATION_URL' => $activationUrl,
			'TOKEN'          => $token,
			'SITENAME'       => $appConfig->get('fromname', 'Panopticon'),
			'SITEURL'        => Uri::base(),
			'EXPIRY_DAYS'    => (string) (int) $appConfig->get('user_registration_activation_days', 7),
		]);
		$data->set('recipient_id', $user->getId());

		$this->enqueueEmail($data, null);
	}

	/**
	 * Send the "account approved/activated" email to the user.
	 *
	 * @param   User  $user  The user
	 *
	 * @return  void
	 */
	private function sendRegistrationApprovedEmail(User $user): void
	{
		$container = Factory::getContainer();
		$data      = new Registry();

		$loginUrl = Uri::base() . $container->router->route('index.php?view=login');

		$data->set('template', 'registration_approved');
		$data->set('email_variables', [
			'NAME'      => $user->getName(),
			'USERNAME'  => $user->getUsername(),
			'EMAIL'     => $user->getEmail(),
			'SITENAME'  => $container->appConfig->get('fromname', 'Panopticon'),
			'SITEURL'   => Uri::base(),
			'LOGIN_URL' => $loginUrl,
		]);
		$data->set('recipient_id', $user->getId());

		$this->enqueueEmail($data, null);
	}

	/**
	 * Send the "registration expired" email to the user.
	 *
	 * @param   User  $user  The user
	 *
	 * @return  void
	 */
	private function sendRegistrationExpiredEmail(User $user): void
	{
		$container = Factory::getContainer();
		$data      = new Registry();

		$data->set('template', 'registration_expired');
		$data->set('email_variables', [
			'NAME'     => $user->getName(),
			'USERNAME' => $user->getUsername(),
			'EMAIL'    => $user->getEmail(),
			'SITENAME' => $container->appConfig->get('fromname', 'Panopticon'),
			'SITEURL'  => Uri::base(),
		]);
		$data->set('recipient_id', $user->getId());

		$this->enqueueEmail($data, null);
	}

	protected function onBeforeDelete($id): void
	{
		$mySelf = $this->container->userManager->getUser();

		// Cannot delete myself
		if ($id == $mySelf->getId())
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_USERS_ERR_CANT_DELETE_YOURSELF'), 403);
		}

		// Cannot delete the last Superuser
		if ($this->isLastSuperUserAccount($id))
		{
			// Normally this should've been caught by "cannot delete myself", but being overly cautious never hurt anyone.
			throw new RuntimeException('PANOPTICON_USERS_ERR_CANT_DELETE_LAST_SUPER', 403);
		}
	}

	private function isLastSuperUserAccount(int $id): bool
	{
		$thatUser = $this->container->userManager->getUser($id);

		if ($thatUser->getId() != $id || !$thatUser->getPrivilege('panopticon.super'))
		{
			return false;
		}

		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select('COUNT(*)')
			->from('#__users')
			->where($db->quoteName('id') . ' != ' . $db->quote((int) $id));
		$query->where($query->jsonExtract($db->quoteName('parameters'), '$.acl.panopticon.super') . ' = TRUE');

		$howManySuperUsersLeft = $db->setQuery($query)->loadResult() ?: 0;

		return $howManySuperUsersLeft < 1;
	}
}