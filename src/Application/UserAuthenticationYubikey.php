<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Download\Download;
use Awf\Uri\Uri;
use Exception;

class UserAuthenticationYubikey extends UserAuthenticationOtep
{
	public function onAuthentication($params = []): bool
	{
		$userParams = $this->user->getParameters();

		if ($userParams->get('tfa.method', 'none') !== 'yubikey')
		{
			return true;
		}

		$result = false;
		$secret = $params['secret'] ?? '';

		if (!empty($secret))
		{
			$result = $this->validateYubikeyOTP($secret);

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

		if ($tfaMethod != 'yubikey')
		{
			return true;
		}

		// The YubiKey code set by the user in the form
		$newCode = $tfaParams['yubikey'] ?? '';
		// The YubiKey code in the user object
		$oldCode = $this->user->getParameters()->get('tfa.yubikey', '');
		// What was the old TFA method?
		$oldTfaMethod = $this->user->getParameters()->get('tfa.method');

		if (($oldTfaMethod === 'yubikey') && ($newCode === $oldCode))
		{
			// We had already set up YubiKey and the code is unchanged. No change performed here.
			return true;
		}

		// Safe fallback until we can verify the new yubikey
		$this->user->getParameters()->set('tfa', null);
		$this->user->getParameters()->set('tfa.method', 'none');

		if (!empty($newCode) && $this->validateYubikeyOTP($newCode))
		{
			$this->user->getParameters()->set('tfa.method', 'yubikey');
			$this->user->getParameters()->set('tfa.yubikey', $newCode);
		}

		return true;
	}

	public function validateYubikeyOTP(string $otp): bool
	{
		$server_queue = [
			'api.yubico.com', 'api2.yubico.com', 'api3.yubico.com',
			'api4.yubico.com', 'api5.yubico.com',
		];

		shuffle($server_queue);

		$gotResponse = false;
		$options     = [];

		$http = new Download();
		$http->setAdapterOptions($options);

		$token    = Factory::getContainer()->session->getCsrfToken()->getValue();
		$nonce    = md5($token . uniqid(rand()));
		$response = '';

		while (!$gotResponse && !empty($server_queue))
		{
			$server = array_shift($server_queue);

			$uri = new Uri('https://' . $server . '/wsapi/2.0/verify');

			// I don't see where this ID is used?
			$uri->setVar('id', 1);

			// The OTP we read from the user
			$uri->setVar('otp', $otp);

			// This prevents a REPLAYED_OTP status of the token doesn't change
			// after a user submits an invalid OTP
			$uri->setVar('nonce', $nonce);

			// Minimum service level required: 50% (at least 50% of the YubiCloud
			// servers must reply positively for the OTP to validate)
			$uri->setVar('sl', 50);

			// Timeou waiting for YubiCloud servers to reply: 5 seconds.
			$uri->setVar('timeout', 5);

			try
			{
				$response = $http->getFromURL($uri->toString());

				if (!empty($response))
				{
					$gotResponse = true;
				}
				else
				{
					continue;
				}
			}
			catch (Exception $exc)
			{
				// No response, continue with the next server
				continue;
			}
		}

		// No server replied; we can't validate this OTP
		if (!$gotResponse)
		{
			return false;
		}

		// Parse response
		$lines = explode("\n", $response);
		$data  = [];

		foreach ($lines as $line)
		{
			$line = trim($line);

			$parts = explode('=', $line, 2);

			if (count($parts) < 2)
			{
				continue;
			}

			$data[$parts[0]] = $parts[1];
		}

		// Validate the response - We need an OK message reply
		if ($data['status'] != 'OK')
		{
			return false;
		}

		// Validate the response - We need a confidence level over 50%
		if ($data['sl'] < 50)
		{
			return false;
		}

		// Validate the response - The OTP must match
		if ($data['otp'] != $otp)
		{
			return false;
		}

		// Validate the response - The token must match
		if ($data['nonce'] != $nonce)
		{
			return false;
		}

		return true;
	}
}
