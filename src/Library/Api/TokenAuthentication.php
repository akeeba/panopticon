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
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Loginfailures;
use Awf\Utils\Ip;

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
	 * Validate a token and return the matched DB row (including user_id and id).
	 *
	 * Always runs at least one `hash_equals` against a dummy value to keep timing flat,
	 * even when the user has zero enabled tokens or the site secret is empty. This
	 * prevents a timing oracle from leaking user-id existence.
	 *
	 * @param   string       $token   The token string to validate.
	 * @param   string|null  $reason  Out: failure reason ('malformed', 'no_secret',
	 *                                'invalid_token', 'expired') for audit logging.
	 *
	 * @return  \stdClass|null  The matched token row on success, or null on failure.
	 * @since   1.4.0
	 */
	public function validateToken(string $token, ?string &$reason = null): ?\stdClass
	{
		$reason = null;

		// Decode the token
		$decoded = base64_decode($token, true);

		if ($decoded === false)
		{
			$reason = 'malformed';
			// Keep timing flat
			hash_equals('dummy-expected-value-for-timing', $token);

			return null;
		}

		// Split into parts: SHA-256:userId:hmac
		$parts = explode(':', $decoded, 3);

		if (count($parts) !== 3)
		{
			$reason = 'malformed';
			hash_equals('dummy-expected-value-for-timing', $token);

			return null;
		}

		[$algorithm, $userIdStr, $hmac] = $parts;

		// Verify algorithm
		if ($algorithm !== 'SHA-256')
		{
			$reason = 'malformed';
			hash_equals('dummy-expected-value-for-timing', $token);

			return null;
		}

		// Verify user ID is a positive integer
		$userId = (int) $userIdStr;

		if ($userId <= 0 || (string) $userId !== $userIdStr)
		{
			$reason = 'malformed';
			hash_equals('dummy-expected-value-for-timing', $token);

			return null;
		}

		// Get the site secret
		$siteSecret = $this->container->appConfig->get('secret', '');

		if (empty($siteSecret))
		{
			$reason = 'no_secret';
			// Burn a hash_equals to keep timing flat with the success path
			hash_equals('dummy-expected-value-for-timing', $token);

			return null;
		}

		// Load all enabled tokens for this user. SQL already filters expired rows.
		/** @var Apitoken $model */
		$model  = $this->container->mvcFactory->makeTempModel('Apitoken');
		$tokens = $model->getEnabledTokensForUser($userId);

		$matchedRow = null;
		$anyMatched = false;

		// Time-safe comparison: always compare against ALL tokens, even after a match.
		foreach ($tokens as $tokenRow)
		{
			// Defensive: belt-and-braces expiry check (the SQL filter is the primary defence).
			if (
				isset($tokenRow->expires_at) && $tokenRow->expires_at !== null
				&& $tokenRow->expires_at !== '' && $tokenRow->expires_at !== '0000-00-00 00:00:00'
				&& strtotime((string) $tokenRow->expires_at) <= time()
			)
			{
				// Still burn a hash_equals to keep timing flat
				hash_equals('dummy-expected-value-for-timing', $token);

				continue;
			}

			$expectedToken = Apitoken::computeToken($tokenRow->seed, $userId, $siteSecret);
			$thisMatched   = hash_equals($expectedToken, $token);

			if ($thisMatched && !$anyMatched)
			{
				$matchedRow = $tokenRow;
				$anyMatched = true;
			}
		}

		// If the user had no enabled tokens (or all were expired), still burn one hash_equals
		// against a dummy value so request timing is flat against the "valid user, has tokens" path.
		if (empty($tokens))
		{
			hash_equals('dummy-expected-value-for-timing', $token);
		}

		if (!$anyMatched)
		{
			$reason = 'invalid_token';

			return null;
		}

		return $matchedRow;
	}

	/**
	 * Authenticate the current request and log in the user for this request only.
	 *
	 * Sets the authenticated user on the in-memory userManager. Does NOT write to the
	 * session segment — API requests do not start a PHP session at all.
	 *
	 * @return  int|null  The authenticated user ID, or null on failure.
	 * @since   1.4.0
	 */
	public function authenticateRequest(): ?int
	{
		$ipBinary = $this->getClientIpBinary();
		$token    = $this->extractToken();

		if ($token === null)
		{
			// No token provided: log audit but do not hit #__login_failures (no attempt).
			AuditLog::record(
				'apitoken.auth_failure',
				null,
				$ipBinary,
				'apitoken',
				null,
				['reason' => 'missing_token']
			);

			return null;
		}

		$reason     = null;
		$matchedRow = $this->validateToken($token, $reason);

		if ($matchedRow === null)
		{
			// Try to surface the user_id from the (probably-malformed) token for the audit row.
			$attemptedUserId = $this->extractUserIdFromToken($token);

			AuditLog::record(
				'apitoken.auth_failure',
				$attemptedUserId,
				$ipBinary,
				'apitoken',
				null,
				['reason' => $reason ?? 'invalid_token']
			);

			// Hit the existing login-failures table so IP lockout machinery kicks in.
			try
			{
				/** @var Loginfailures $loginFailures */
				$loginFailures = $this->container->mvcFactory->makeTempModel('Loginfailures');
				$loginFailures->logFailure();
			}
			catch (\Throwable)
			{
				// Best effort; never break the response.
			}

			return null;
		}

		$userId = (int) $matchedRow->user_id;

		// Log in the user for this request only (no session write).
		$manager = $this->container->userManager;
		$user    = $manager->getUser($userId);

		if (!$user || !$user->getId())
		{
			AuditLog::record(
				'apitoken.auth_failure',
				$userId,
				$ipBinary,
				'apitoken',
				(int) $matchedRow->id,
				['reason' => 'user_missing']
			);

			return null;
		}

		// If the user's effective token limit is 0, API access is denied entirely.
		/** @var Apitoken $apitokenModel */
		$apitokenModel  = $this->container->mvcFactory->makeTempModel('Apitoken');
		$effectiveLimit = $apitokenModel->getEffectiveLimitForUser($userId);

		if ($effectiveLimit === 0)
		{
			AuditLog::record(
				'apitoken.auth_failure',
				$userId,
				$ipBinary,
				'apitoken',
				(int) $matchedRow->id,
				['reason' => 'api_access_denied']
			);

			return null;
		}

		// Set the authenticated user on the in-memory userManager for this request.
		// We use reflection because AWF's Manager has no public setUser() and we MUST NOT
		// write to the session segment (per master plan: API auth is ephemeral, no session).
		try
		{
			$ref      = new \ReflectionObject($manager);
			$property = $ref->getProperty('currentUser');
			$property->setAccessible(true);
			$property->setValue($manager, $user);
		}
		catch (\Throwable)
		{
			// If reflection fails (future AWF refactor), fall back gracefully.
		}

		// Record this token's use (deduplicated to once per 60s in the model).
		try
		{
			/** @var Apitoken $tokenModel */
			$tokenModel = $this->container->mvcFactory->makeTempModel('Apitoken');
			$tokenModel->bind($matchedRow);
			$tokenModel->recordUse($ipBinary);
		}
		catch (\Throwable)
		{
			// Recording use must never break auth.
		}

		AuditLog::record(
			'apitoken.auth_success',
			$userId,
			$ipBinary,
			'apitoken',
			(int) $matchedRow->id
		);

		return $userId;
	}

	/**
	 * Best-effort extraction of the embedded user_id from a token, for audit logging
	 * of failed authentications. Returns null when the token is too malformed to
	 * extract anything safely.
	 *
	 * @param   string  $token
	 *
	 * @return  int|null
	 * @since   1.4.0
	 */
	private function extractUserIdFromToken(string $token): ?int
	{
		$decoded = base64_decode($token, true);

		if ($decoded === false)
		{
			return null;
		}

		$parts = explode(':', $decoded, 3);

		if (count($parts) !== 3)
		{
			return null;
		}

		$userId = (int) $parts[1];

		return $userId > 0 ? $userId : null;
	}

	/**
	 * Get the current client IP packed as a binary string (inet_pton form), or null if unavailable.
	 *
	 * @return  string|null
	 * @since   1.4.0
	 */
	private function getClientIpBinary(): ?string
	{
		try
		{
			$ip = Ip::getUserIP();

			if (empty($ip))
			{
				return null;
			}

			$packed = @inet_pton($ip);

			return $packed === false ? null : $packed;
		}
		catch (\Throwable)
		{
			return null;
		}
	}
}
