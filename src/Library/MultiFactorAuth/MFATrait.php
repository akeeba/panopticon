<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\User\User;
use Exception;

trait MFATrait
{
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

		if (in_array($view, ['cron', 'captive', 'mfamethods', 'login', 'logout']))
		{
			return false;
		}

		// Everything else will be redirected to the MFA captive page
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