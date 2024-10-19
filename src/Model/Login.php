<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Passkey\CredentialRepository;
use Awf\Mvc\Model;

/**
 * Login Page Model
 *
 * It's empty, but it is necessary for it to exist
 *
 * @since  1.0.0
 */
class Login extends Model
{
	/**
	 * Is the user allowed to log into the site with a password?
	 *
	 * @param   string|null  $username  The username to check
	 *
	 * @return  bool
	 * @since   1.2.3
	 */
	public function canUserLoginWithPassword(?string $username): bool
	{
		$user = $this->getContainer()->userManager->getUserByUsername($username);

		// If the user was not found we return a fake "true" so that the regular login process records a fail.
		if (!$user->getId())
		{
			return true;
		}

		// This only applies if the user has passkeys on their account.
		$hasPasskeys = count((new CredentialRepository())->getAll($user->getId())) > 0;

		if (!$hasPasskeys)
		{
			return true;
		}

		// Get the global option
		$loginNoPasswordGlobal = $this->getContainer()->appConfig->get('passkey_login_no_password', 'user');

		return match ($loginNoPasswordGlobal)
		{
			'always' => false,
			'never' => true,
			'user' => !$user->getParameters()->get('passkey_login_no_password', false),
		};
	}

	/**
	 * Reset the password reset information
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function resetPasswordResetRequests(): void
	{
		$userManager = $this->getContainer()->userManager;
		$user        = $userManager->getUser();

		if (!$user->getId())
		{
			return;
		}

		$user->getParameters()->set('pwreset.timestamp', 0);
		$user->getParameters()->set('pwreset.count', 0);
		$user->getParameters()->set('pwreset.secret', '');

		$userManager->saveUser($user);
	}
}