<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Captcha;

defined('AKEEBA') || die;

use Awf\Container\Container;

/**
 * Self-hosted ALTCHA proof-of-work CAPTCHA implementation.
 *
 * The challenge works as follows:
 * 1. Server generates a random salt and a secret number (0 to maxNumber).
 * 2. Server computes: challenge = SHA-256(salt + secretNumber)
 * 3. Server signs the challenge with an HMAC-SHA-256 using a server secret key.
 * 4. Client receives {algorithm, challenge, salt, maxnumber, signature} and brute-forces
 *    the secret number by iterating from 0 to maxnumber, hashing salt + i until it matches.
 * 5. Client submits the found number. Server verifies by recomputing the hash.
 */
class AltchaCaptcha implements CaptchaInterface
{
	private const MAX_NUMBER = 100000;

	private const ALGORITHM = 'SHA-256';

	public function __construct(
		private readonly Container $container
	)
	{
	}

	public function getName(): string
	{
		return 'altcha';
	}

	public function getLabel(): string
	{
		return 'ALTCHA (self-hosted)';
	}

	public function renderChallenge(): string
	{
		$challenge = $this->generateChallenge();

		// Store challenge in session so we can validate later
		$this->container->segment->set('altcha_challenge', json_encode($challenge));

		$challengeJson = htmlspecialchars(json_encode($challenge), ENT_QUOTES, 'UTF-8');

		return <<<HTML
<script src="media/altcha/altcha.js" defer></script>
<altcha-widget challengejson="{$challengeJson}" auto="onsubmit"></altcha-widget>
HTML;
	}

	public function validateResponse(): bool
	{
		$input = $this->container->input;

		// Get the submitted payload
		$payloadEncoded = $input->get('altcha', '', 'raw');

		if (empty($payloadEncoded))
		{
			return false;
		}

		// Decode the payload (base64-encoded JSON)
		try
		{
			$payload = json_decode(base64_decode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\Throwable)
		{
			return false;
		}

		if (
			!isset($payload['algorithm'], $payload['challenge'], $payload['number'], $payload['salt'], $payload['signature'])
		)
		{
			return false;
		}

		// Get the stored challenge from session
		$storedJson = $this->container->segment->get('altcha_challenge', '');
		$this->container->segment->set('altcha_challenge', '');

		if (empty($storedJson))
		{
			return false;
		}

		try
		{
			$stored = json_decode($storedJson, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\Throwable)
		{
			return false;
		}

		// Verify the challenge matches what we stored
		if ($payload['challenge'] !== $stored['challenge'] || $payload['salt'] !== $stored['salt'])
		{
			return false;
		}

		// Verify the signature
		$serverSecret = $this->getServerSecret();
		$expectedSignature = hash_hmac('sha256', $payload['challenge'], $serverSecret);

		if (!hash_equals($expectedSignature, $payload['signature']))
		{
			return false;
		}

		// Verify the solution: hash(salt + number) must equal the challenge
		$number = (int) $payload['number'];
		$expectedChallenge = hash('sha256', $payload['salt'] . $number);

		return hash_equals($expectedChallenge, $payload['challenge']);
	}

	/**
	 * Generate a challenge for the client to solve.
	 *
	 * @return  array{algorithm: string, challenge: string, salt: string, maxnumber: int, signature: string}
	 */
	private function generateChallenge(): array
	{
		$salt         = bin2hex(random_bytes(16));
		$secretNumber = random_int(0, self::MAX_NUMBER);
		$challenge    = hash('sha256', $salt . $secretNumber);
		$serverSecret = $this->getServerSecret();
		$signature    = hash_hmac('sha256', $challenge, $serverSecret);

		return [
			'algorithm' => self::ALGORITHM,
			'challenge' => $challenge,
			'salt'      => $salt,
			'maxnumber' => self::MAX_NUMBER,
			'signature' => $signature,
		];
	}

	/**
	 * Get the server secret key for signing challenges.
	 *
	 * Uses the session token algorithm config + a hash of the database password to derive a unique secret.
	 *
	 * @return  string
	 */
	private function getServerSecret(): string
	{
		$secret = $this->container->appConfig->get('session_token_algorithm', 'sha512');
		$dbPass = $this->container->appConfig->get('dbpass', '');

		return hash('sha256', $secret . ':' . $dbPass . ':altcha_captcha_secret');
	}
}
