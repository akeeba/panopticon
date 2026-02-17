<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Api;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Model\Apitoken;

/**
 * API Token Authentication
 *
 * Extracts, validates, and authenticates API bearer tokens from HTTP requests.
 *
 * @since  1.4.0
 */
class TokenAuthentication
{
	public function __construct(
		private readonly Container $container
	) {}

	/**
	 * Extract the API token from the current request.
	 *
	 * Checks (first found wins):
	 * 1. Authorization: Bearer <token> header
	 * 2. X-Panopticon-Token: <token> header
	 * 3. $_GET['_panopticon_token'] query parameter
	 *
	 * @return  string|null  The token string, or null if not found.
	 * @since   1.4.0
	 */
	public function extractToken(): ?string
	{
		// 1. Authorization: Bearer header
		$authHeader = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? null;

		if ($authHeader !== null && stripos($authHeader, 'Bearer ') === 0)
		{
			$token = trim(substr($authHeader, 7));

			if ($token !== '')
			{
				return $token;
			}
		}

		// 2. X-Panopticon-Token header
		$customHeader = $_SERVER['HTTP_X_PANOPTICON_TOKEN'] ?? null;

		if ($customHeader !== null && trim($customHeader) !== '')
		{
			return trim($customHeader);
		}

		// 3. Query parameter
		$queryToken = $_GET['_panopticon_token'] ?? null;

		if ($queryToken !== null && trim($queryToken) !== '')
		{
			return trim($queryToken);
		}

		return null;
	}

	/**
	 * Validate a token and return the authenticated user ID.
	 *
	 * @param   string  $token  The token string to validate.
	 *
	 * @return  int|null  The user ID on success, or null on failure.
	 * @since   1.4.0
	 */
	public function validateToken(string $token): ?int
	{
		// Decode the token
		$decoded = base64_decode($token, true);

		if ($decoded === false)
		{
			return null;
		}

		// Split into parts: SHA-256:userId:hmac
		$parts = explode(':', $decoded, 3);

		if (count($parts) !== 3)
		{
			return null;
		}

		[$algorithm, $userIdStr, $hmac] = $parts;

		// Verify algorithm
		if ($algorithm !== 'SHA-256')
		{
			return null;
		}

		// Verify user ID is a positive integer
		$userId = (int) $userIdStr;

		if ($userId <= 0 || (string) $userId !== $userIdStr)
		{
			return null;
		}

		// Get the site secret
		$siteSecret = $this->container->appConfig->get('secret', '');

		if (empty($siteSecret))
		{
			return null;
		}

		// Load all enabled tokens for this user
		/** @var Apitoken $model */
		$model  = $this->container->mvcFactory->makeTempModel('Apitoken');
		$tokens = $model->getEnabledTokensForUser($userId);

		if (empty($tokens))
		{
			return null;
		}

		// Time-safe comparison: always compare against ALL tokens
		$matched = array_map(
			function ($tokenRow) use ($siteSecret, $userId, $token)
			{
				$expectedToken = Apitoken::computeToken($tokenRow->seed, $userId, $siteSecret);

				return hash_equals($expectedToken, $token);
			},
			$tokens
		);

		// Check if any token matched
		$anyMatched = array_reduce(
			$matched,
			fn(bool $carry, bool $result) => $carry || $result,
			false
		);

		return $anyMatched ? $userId : null;
	}

	/**
	 * Authenticate the current request and log in the user for this request only.
	 *
	 * @return  int|null  The authenticated user ID, or null on failure.
	 * @since   1.4.0
	 */
	public function authenticateRequest(): ?int
	{
		$token = $this->extractToken();

		if ($token === null)
		{
			return null;
		}

		$userId = $this->validateToken($token);

		if ($userId === null)
		{
			return null;
		}

		// Log in the user for this request (no persistent session)
		$manager = $this->container->userManager;
		$user    = $manager->getUser($userId);

		if (!$user->getId())
		{
			return null;
		}

		$this->container->segment->set('user_id', $userId);

		return $userId;
	}
}
