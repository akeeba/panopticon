<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\User\Authentication;

abstract class UserAuthenticationOtep extends Authentication
{
	public function validateOtep(string $otp): bool
	{
		// Get the OTEPs
		$oteps = $this->user->getParameters()->get('tfa.otep', []);

		// If there is no OTEP we can't authenticate
		if (empty($oteps))
		{
			return false;
		}

		$oteps = (array) $oteps;

		// Does this OTEP exist in the list?
		$tempOtp = preg_filter('/\D/', '', $otp);
		$otp     = is_null($tempOtp) ? $otp : $tempOtp;

		// No. Can't authenticate.
		if (!in_array($otp, $oteps))
		{
			return false;
		}

		// Remove the OTEP from the list
		$temp      = [];

		// Ugly as heck, but PHP freaks out with the number-as-string array indexes it produces.
		foreach ($oteps as $foo)
		{
			if ($foo == $otp)
			{
				continue;
			}

			$temp[] = $foo;
		}

		// Save the modified user
		$this->user->getParameters()->set('tfa.otep', $temp);

		$userManager = Factory::getContainer()->userManager;
		$userManager->saveUser($this->user);

		// OK, we can authenticate
		return true;
	}
}
