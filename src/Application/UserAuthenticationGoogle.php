<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Awf\Encrypt\Totp;

class UserAuthenticationGoogle extends UserAuthenticationOtep
{
	public function onAuthentication($params = []): bool
	{
		$result     = true;
		$userParams = $this->user->getParameters();

		if ($userParams->get('tfa.method', 'none') === 'google')
		{
			$result = false;
			$secret = $params['secret'] ?? '';

			if (empty($secret))
			{
				return $result;
			}

			$result = $this->validateGoogleOTP($secret);

			if (!$result)
			{
				$result = $this->validateOtep($secret);
			}
		}

		return $result;
	}

	public function onTfaSave(array $tfaParams): bool
	{
		$tfaMethod = $tfaParams['method'] ?? '';

		if ($tfaMethod == 'google')
		{
			// The Google Authenticator key set by the user in the form
			$newKey = $tfaParams['google'] ?? '';
			// The Google Authenticator key in the user object
			$oldKey = $this->user->getParameters()->get('tfa.google', '');
			// The Google Authenticator generated secret code given in the form
			$secret = $tfaParams['secret'] ?? '';
			// What was the old TFA method?
			$oldTfaMethod = $this->user->getParameters()->get('tfa.method');

			if (($oldTfaMethod === 'google') && ($newKey === $oldKey))
			{
				// We had already set up Google Authenticator and the code is unchanged. No change performed here.
				return true;
			}

			// Safe fallback until we can verify the new yubikey
			$this->user->getParameters()->set('tfa', null);
			$this->user->getParameters()->set('tfa.method', 'none');

			if (!empty($secret) && $this->validateGoogleOTP($secret, $newKey))
			{
				$this->user->getParameters()->set('tfa.method', 'google');
				$this->user->getParameters()->set('tfa.google', $newKey);
			}
		}

		return true;
	}

	public function validateGoogleOTP(string $otp, ?string $key = null): bool
	{
		// Create a new TOTP class with Google Authenticator compatible settings
		$totp = new Totp(30, 6, 10);

		// Get the key if none is defined
		if (empty($key))
		{
			$key = $this->user->getParameters()->get('tfa.google', '');
		}

		// Check the code
		$code = $totp->getCode($key);

		$check = $code == $otp;

		/*
		 * If the check fails, test the previous 30 second slot. This allow the
		 * user to enter the security code when it's becoming red in Google
		 * Authenticator app (reaching the end of its 30 second lifetime)
		 */
		if (!$check)
		{
			$time  = time() - 30;
			$code  = $totp->getCode($key, $time);
			$check = $code == $otp;
		}

		/*
		 * If the check fails, test the next 30 second slot. This allows some
		 * time drift between the authentication device and the server
		 */
		if (!$check)
		{
			$time  = time() + 30;
			$code  = $totp->getCode($key, $time);
			$check = $code == $otp;
		}

		return $check;
	}
}
