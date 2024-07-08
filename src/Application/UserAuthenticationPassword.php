<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Awf\User\Authentication;

class UserAuthenticationPassword extends Authentication
{
	public function onAuthentication($params = []): bool
	{
		$password       = $params['password'] ?? '';
		$hashedPassword = $this->user->getPassword();

		if (str_starts_with($hashedPassword, '$2y$'))
		{
			return password_verify($password, $hashedPassword);
		}

		$parts = explode(':', $hashedPassword, 3);

		switch ($parts[0])
		{
			case 'SHA512':
				return $this->timingSafeEquals($parts[1], hash('sha512', $password . $parts[2], false));

			case 'SHA256':
				return $this->timingSafeEquals($parts[1], hash('sha256', $password . $parts[2], false));

			case 'SHA1':
				return $this->timingSafeEquals($parts[1], hash('sha1', $password . $parts[2]));

			case 'MD5':
				return $this->timingSafeEquals($parts[1], hash('md5', $password . $parts[2]));
		}

		// If all else fails, we assume we can't verify this password
		return false;
	}

	public function onTfaSave(array $tfaParams): bool
	{
		$tfaMethod = $tfaParams['method'] ?? '';

		if ($tfaMethod == 'none')
		{
			$this->user->getParameters()->set('tfa', null);
			$this->user->getParameters()->set('tfa.method', 'none');
		}

		return true;
	}

	protected function timingSafeEquals(string $safe, string $user): bool
	{
		// Prevent issues if string length is 0
		$safe .= chr(0);
		$user .= chr(0);

		$safeLen = strlen($safe);
		$userLen = strlen($user);

		// Set the result to the difference between the lengths
		$result = $safeLen - $userLen;

		// Note that we ALWAYS iterate over the user-supplied length
		// This is to prevent leaking length information
		for ($i = 0; $i < $userLen; $i++)
		{
			// Using % here is a trick to prevent notices
			// It's safe, since if the lengths are different
			// $result is already non-0
			$result |= (ord($safe[$i % $safeLen]) ^ ord($user[$i]));
		}

		// They are only identical strings if $result is exactly 0...
		return $result === 0;
	}
}
