<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Awf\Date\Date;
use Awf\Mvc\DataModel;

/**
 * Model for API tokens
 *
 * @property int         $id          Record ID.
 * @property int         $user_id     User ID the token is for.
 * @property string|null $description Token description.
 * @property string      $seed        Base64-encoded random seed.
 * @property int         $enabled     Is the token enabled?
 * @property int         $created_by  User who created the token.
 * @property Date        $created_on  When the token was created.
 * @property int|null    $modified_by User who last modified the token.
 * @property Date|null   $modified_on When the token was last modified.
 * @property Date|null   $expires_at  Token expiry timestamp; NULL means never expires.
 * @property Date|null   $last_used_at Last time this token authenticated a request.
 * @property string|null $last_used_ip Last client IP (binary inet_pton, 16 bytes).
 * @property string|null $scopes      Reserved JSON-encoded list of granted scopes.
 *
 * @since  1.4.0
 */
class Apitoken extends DataModel
{
	/**
	 * Maximum number of enabled, non-expired API tokens per user. Prevents an attacker
	 * who can mint tokens via a compromised UI session from creating thousands of tokens,
	 * which would slow every authenticated request via the O(N) HMAC validation loop.
	 *
	 * @since  1.4.0
	 */
	public const MAX_PER_USER = 50;

	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__api_tokens';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	/**
	 * Build the SELECT query for the list view, applying the filter bar state.
	 *
	 * @param   bool  $overrideLimits  Set to true to override the limits.
	 *
	 * @return  \Awf\Database\Query
	 * @since   2.1.0
	 */
	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);
		$db    = $this->getDbo();

		// Restrict to the current user's tokens unless they are a super user.
		$user = $this->getContainer()->userManager->getUser();

		if (!$user->getPrivilege('panopticon.super'))
		{
			$query->where(
				$db->quoteName('user_id') . ' = ' . $db->quote((int) $user->getId())
			);
		}

		$search = trim((string) ($this->getState('search', '') ?? ''));

		if ($search !== '')
		{
			$query->where(
				$db->quoteName('description') . ' LIKE ' . $db->quote('%' . $search . '%')
			);
		}

		$enabled = $this->getState('enabled', null);

		if ($enabled !== null && $enabled !== '')
		{
			$query->where(
				$db->quoteName('enabled') . ' = ' . $db->quote((int) $enabled)
			);
		}

		$createdAfter = trim((string) ($this->getState('created_after', '') ?? ''));

		if ($createdAfter !== '')
		{
			try
			{
				$sql = $this->getContainer()->dateFactory($createdAfter)->toSql();
				$query->where(
					$db->quoteName('created_on') . ' >= ' . $db->quote($sql)
				);
			}
			catch (\Throwable)
			{
				// Ignore invalid date input.
			}
		}

		$expiresBefore = trim((string) ($this->getState('expires_before', '') ?? ''));

		if ($expiresBefore !== '')
		{
			try
			{
				$sql = $this->getContainer()->dateFactory($expiresBefore)->toSql();
				$query->where(
					$db->quoteName('expires_at') . ' IS NOT NULL'
				);
				$query->where(
					$db->quoteName('expires_at') . ' <= ' . $db->quote($sql)
				);
			}
			catch (\Throwable)
			{
				// Ignore invalid date input.
			}
		}

		$lastUsedBefore = trim((string) ($this->getState('last_used_before', '') ?? ''));

		if ($lastUsedBefore !== '')
		{
			try
			{
				$sql = $this->getContainer()->dateFactory($lastUsedBefore)->toSql();
				$query->where(
					$db->quoteName('last_used_at') . ' IS NOT NULL'
				);
				$query->where(
					$db->quoteName('last_used_at') . ' <= ' . $db->quote($sql)
				);
			}
			catch (\Throwable)
			{
				// Ignore invalid date input.
			}
		}

		return $query;
	}

	/**
	 * Generate a random seed for a new API token.
	 *
	 * @return  string  Base64-encoded random 64 bytes.
	 * @since   1.4.0
	 */
	public static function generateSeed(): string
	{
		return base64_encode(random_bytes(64));
	}

	/**
	 * Compute the API token string from a seed and user ID.
	 *
	 * @param   string  $seed        The base64-encoded seed.
	 * @param   int     $userId      The user ID.
	 * @param   string  $siteSecret  The site secret key.
	 *
	 * @return  string  The API token string (base64-encoded).
	 * @since   1.4.0
	 */
	public static function computeToken(string $seed, int $userId, string $siteSecret): string
	{
		return base64_encode(
			'SHA-256:' . $userId . ':' . hash_hmac('sha256', base64_decode($seed), $siteSecret)
		);
	}

	/**
	 * Get all enabled tokens for a given user ID.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  array  Array of Apitoken objects.
	 * @since   1.4.0
	 */
	public function getEnabledTokensForUser(int $userId): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__api_tokens'))
			->where($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId))
			->where(
				'(' . $db->quoteName('expires_at') . ' IS NULL OR '
				. $db->quoteName('expires_at') . ' > NOW())'
			);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Has this token expired?
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	public function isExpired(): bool
	{
		$expiresAt = $this->expires_at;

		if ($expiresAt === null || $expiresAt === '' || $expiresAt === '0000-00-00 00:00:00')
		{
			return false;
		}

		try
		{
			$expiry = $expiresAt instanceof Date
				? $expiresAt
				: $this->getContainer()->dateFactory($expiresAt);

			return $expiry->getTimestamp() < time();
		}
		catch (\Throwable)
		{
			return false;
		}
	}

	/**
	 * Record this token's use, at most once per 60 seconds (dedup avoids hot writes).
	 *
	 * @param   string|null  $ipBinary  The client IP as a packed (inet_pton) binary string, or NULL.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function recordUse(?string $ipBinary): void
	{
		if (!$this->id)
		{
			return;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__api_tokens'))
			->set($db->quoteName('last_used_at') . ' = NOW()')
			->set($db->quoteName('last_used_ip') . ' = ' . ($ipBinary === null ? 'NULL' : $db->quote($ipBinary)))
			->where($db->quoteName('id') . ' = ' . $db->quote($this->id))
			->where(
				'(' . $db->quoteName('last_used_at') . ' IS NULL OR '
				. $db->quoteName('last_used_at') . ' < NOW() - INTERVAL 60 SECOND)'
			);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// Non-fatal: failing to record usage must never break authentication.
		}
	}

	/**
	 * Count enabled (non-expired) tokens for a user, for the per-user cap check.
	 *
	 * @param   int  $userId
	 *
	 * @return  int
	 * @since   1.4.0
	 */
	public function countEnabledForUser(int $userId): int
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__api_tokens'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId))
			->where($db->quoteName('enabled') . ' = 1')
			->where(
				'(' . $db->quoteName('expires_at') . ' IS NULL OR '
				. $db->quoteName('expires_at') . ' > NOW())'
			);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Get all tokens for a given user ID.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  array  Array of token row objects.
	 * @since   1.4.0
	 */
	public function getTokensForUser(int $userId): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__api_tokens'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId))
			->order($db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}
}
