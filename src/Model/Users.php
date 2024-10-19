<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\MultiFactorAuth\MFATrait;
use Akeeba\Panopticon\Library\Passkey\PasskeyTrait;
use Akeeba\Panopticon\Model\Trait\UserAvatarTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Awf\Container\Container;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
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

	public function __construct(Container $container = null)
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
			$pwResetSecret = @base64_decode($pwResetSecretEncoded);
		}
		catch (\Exception $e)
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