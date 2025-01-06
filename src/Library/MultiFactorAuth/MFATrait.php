<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\User\User;
use Awf\User\UserInterface;
use Exception;

trait MFATrait
{
	private $mfaAllowedViews = ['cron', 'captive', 'mfamethods', 'passkeys', 'login', 'logout'];

	/**
	 * Does the user need to add any new MFA records to have at least one valid record?
	 *
	 * @param   null|User  $user  The user object
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public function userNeedsMFARecords(?User $user = null): bool
	{
		static $records = null, $lastUserId = null;

		$user ??= Factory::getContainer()->userManager->getUser();

		if (empty($user) || empty($user->getId()))
		{
			// Guest cannot have MFA
			return false;
		}

		if ($lastUserId != $user->getId())
		{
			$lastUserId = $user->getId();
			$records    = Helper::getUserMfaRecords($this->getContainer(), $user->getId());
		}

		// No MFA Methods?
		if (count($records) < 1)
		{
			return true;
		}

		// Let's get a list of all currently active MFA Methods
		$mfaMethods = Helper::getMfaMethods();

		// If no MFA Method is active we can't really display a Captive login page.
		if (empty($mfaMethods))
		{
			return false;
		}

		// Get a list of just the Method names
		$methodNames = [];

		foreach ($mfaMethods as $mfaMethod)
		{
			$methodNames[] = $mfaMethod->name;
		}

		// The user will need a new MFA record if no existing record uses a valid MFA method.
		return !array_reduce(
			$records,
			fn(bool $carry, object $record) => $carry || in_array($record->method, $methodNames),
			false
		);
	}

	/**
	 * Does the user need to complete MFA authentication before being allowed to access the site?
	 *
	 * @param   null|User  $user  The user object
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	private function needsMFA(?User $user = null): bool
	{
		static $records = null, $lastUserId = null;

		$user ??= Factory::getContainer()->userManager->getUser();

		if (empty($user) || empty($user->getId()))
		{
			return false;
		}

		if ($lastUserId != $user->getId())
		{
			$lastUserId = $user->getId();
			$records    = Helper::getUserMfaRecords($this->getContainer(), $user->getId());
		}

		// No MFA Methods? Then we obviously don't need to display a Captive login page.
		if (count($records) < 1)
		{
			return false;
		}

		// Let's get a list of all currently active MFA Methods
		$mfaMethods = Helper::getMfaMethods();

		// If no MFA Method is active we can't really display a Captive login page.
		if (empty($mfaMethods))
		{
			return false;
		}

		// Get a list of just the Method names
		$methodNames = [];

		foreach ($mfaMethods as $mfaMethod)
		{
			$methodNames[] = $mfaMethod->name;
		}

		// Filter the records based on currently available MFA methods
		return array_reduce(
			$records,
			function (bool $carry, $record) use ($methodNames) {
				return $carry || in_array($record->method, $methodNames);
			},
			false
		);
	}

	/**
	 * Check whether we'll need to do a redirection to the Captive page.
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 *
	 */
	private function needsRedirectToCaptive(): bool
	{
		if ($this->getMfaCheckedFlag())
		{
			return false;
		}

		// Do not redirect if there is no need to go through MFA
		if (!$this->needsMFA())
		{
			$this->setMfaCheckedFlag(true);

			return false;
		}

		// Always allow access to the cron, captive, MFA method select, login, and logout pages
		$view = strtolower(Factory::getContainer()->input->get('view', 'main'));

		if (in_array($view, $this->mfaAllowedViews))
		{
			return false;
		}

		// Everything else will be redirected to the MFA captive page
		return true;
	}

	private function isForcedMFAEnabled(UserInterface $user = null): bool
	{
		$user ??= Factory::getContainer()->userManager->getUser();

		if (empty($user) || empty($user->getId()))
		{
			// Guest cannot have MFA
			return false;
		}

		$appConfig = $this->getContainer()->appConfig;

		// Is the user a Superuser and mfa_superuser enabled?
		if ($appConfig->get('mfa_superuser', false)
		    && $user->getPrivilege('panopticon.super'))
		{
			return true;
		}

		// Is the user an Administrator and mfa_admin enabled?
		if ($appConfig->get('mfa_admin', false)
		    && $user->getPrivilege('panopticon.admin'))
		{
			return true;
		}

		// Is the user in one of the forced MFA groups?
		$forcedMFAGroups = $appConfig->get('mfa_force_groups', []);
		$forcedMFAGroups = is_array($forcedMFAGroups) ? array_values($forcedMFAGroups) : [];
		$userGroups      = $user->getParameters()->get('usergroups', []);
		$userGroups      = is_array($userGroups) ? array_values($userGroups) : [];
		$intersection    = array_intersect($forcedMFAGroups, $userGroups);

		if (!empty($forcedMFAGroups) && !empty($userGroups) && !empty($intersection))
		{
			return true;
		}

		return false;
	}

	private function needsMFAForcedSetup(): bool
	{
		// If the user has already set up MFA they don't need to, um, set up MFA. Makes sense.
		if (!$this->userNeedsMFARecords())
		{
			return false;
		}

		// If the user is not logged in they cannot set up MFA.
		$user = Factory::getContainer()->userManager->getUser();

		if (empty($user) || empty($user->getId()))
		{
			return false;
		}

		if (!$this->isForcedMFAEnabled($user))
		{
			return false;
		}

		// If we are in an MFA-transparent view we won't redirect.
		$view = strtolower(Factory::getContainer()->input->get('view', 'main'));

		if (in_array($view, $this->mfaAllowedViews))
		{
			return false;
		}

		// Allow the view where the user sets up new MFA methods
		if ($view === 'mfamethod')
		{
			return false;
		}

		// If we are already in the user edit, save, or apply task we return false to prevent a redirect loop.
		$task = strtolower(Factory::getContainer()->input->get('task', 'main'));

		if (($view === 'users' || $view === 'user') && in_array($task, ['edit', 'apply', 'save']))
		{
			return false;
		}

		return true;
	}

	private function setMfaCheckedFlag(bool $value): void
	{
		Factory::getContainer()->segment->set('panopticon.mfa_checked', $value);
	}

	private function getMfaCheckedFlag(): bool
	{
		return (bool) Factory::getContainer()->segment->get('panopticon.mfa_checked', false);
	}
}