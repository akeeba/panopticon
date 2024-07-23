<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Utils\Ip;
use Exception;

/**
 * Handles login rate limiting
 *
 * @since 1.2.0
 */
class Loginfailures extends Model
{
	static ?bool $isAvailable = null;

	/**
	 * Log a failed login attempt.
	 *
	 * @param   bool  $autoBlock  Should I process IP-based blocking automatically?
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.2.0
	 */
	public function logFailure(bool $autoBlock = true): void
	{
		if (!$this->isAvailable())
		{
			return;
		}

		// Is the feature enabled?
		if (!((bool) $this->getContainer()->appConfig->get('login_failure_enable', 1)))
		{
			return;
		}

		// Make sure we have a current IP
		$ip = Ip::getUserIP();

		if (empty($ip))
		{
			return;
		}

		/**
		 * Reasoning behind this code:
		 *
		 * “The correct way to use LOCK TABLES and UNLOCK TABLES with transactional tables, such as InnoDB tables, is to
		 * begin a transaction with SET autocommit = 0 (not START TRANSACTION) followed by LOCK TABLES, and to not call
		 * UNLOCK TABLES until you commit the transaction explicitly.”
		 *
		 * This is meant to avoid deadlocks.
		 *
		 * @see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		 */
		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__login_failures');

		$query = $db->getQuery(true)
			->insert('#__login_failures')
			->columns(
				[
					$db->quoteName('ip'),
					$db->quoteName('mark'),
				]
			)
			->values(
				'INET6_ATON(' . $db->quote($ip) . '),' .
				'CURRENT_TIMESTAMP()'
			);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			return;
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();
		}

		if (!$autoBlock)
		{
			return;
		}

		if ($this->mustBeBlocked())
		{
			$this->blockIp();

			BootstrapUtilities::evaluateIPBlocking();
		}
	}

	/**
	 * Clean up old login failures from the database.
	 *
	 * This method deletes old login failures for a given IP address.
	 * It uses the provided maximum window of time to determine which failures to delete.
	 *
	 * @return  void
	 * @since   1.2.0
	 */
	public function cleanupOldFailures(): void
	{
		if (!$this->isAvailable())
		{
			return;
		}

		// Is the feature enabled?
		if (!((bool) $this->getContainer()->appConfig->get('login_failure_enable', 1)))
		{
			return;
		}


		// Make sure we have a current IP
		$ip = Ip::getUserIP();

		if (empty($ip))
		{
			return;
		}

		$appConfig  = $this->getContainer()->appConfig;
		$maxSeconds = max(1, $appConfig->get('login_failure_window', 60));

		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__login_failures');

		$query = $db->getQuery(true)
			->delete('#__login_failures')
			->where(
				[
					$db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')',
					$db->quoteName('mark') . ' < DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL ' . intval($maxSeconds)
					. ' SECOND)',
				]
			);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			return;
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();
		}

	}

	/**
	 * Check if the current user's IP address is blocked.
	 *
	 * If the IP address is blocked, and the lockout has not been reached, it is extended. This means that the lockout
	 * time becomes the current date and time plus the lockout period. This is controlled by the login_lockout_extend
	 * application configuration parameter.
	 *
	 * If the IP address is blocked, but the lockout time has elapsed, the lockout record is removed.
	 *
	 * @return  bool  Returns true if the IP is blocked, false otherwise.
	 *
	 * @throws  Exception  If an error occurs during the query execution.
	 * @since   1.2.0
	 */
	public function isIPBlocked(): bool
	{
		if (!$this->isAvailable())
		{
			return false;
		}

		// Is the feature enabled?
		if (!((bool) $this->getContainer()->appConfig->get('login_failure_enable', 1)))
		{
			return false;
		}

		// Make sure we have a current IP
		$ip = Ip::getUserIP();

		if (empty($ip))
		{
			return false;
		}

		$appConfig   = $this->getContainer()->appConfig;
		$lockoutTime = max(0, $appConfig->get('login_lockout', 900));
		$extend      = (bool) $appConfig->get('login_lockout_extend', 0);

		// Is the IP already blocked?
		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__login_lockouts');

		$query = $db->getQuery(true)
			->select($db->quoteName('until'))
			->from('#__login_lockouts')
			->where($db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')');

		try
		{
			$until = $db->setQuery($query)->loadResult();

			// There is no lockout. Return false.
			if (empty($until))
			{
				return false;
			}

			// Is the lock still valid?
			$dUntil = new Date($until, 'GMT', $this->container);
			$dNow   = new Date('now', 'GMT', $this->container);

			// The lock has expired. Remove it and return false.
			if ($dUntil < $dNow)
			{
				$delQuery = $db->getQuery(true)
					->delete($db->quoteName('#__login_lockouts'))
					->where($db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')');

				$db->setQuery($delQuery)->execute();

				return false;
			}

			// The lock is still valid. Check whether I should extend the lockout.
			if ($extend)
			{
				$extendQuery = $db->getQuery(true)
					->update($db->quoteName('#__login_lockouts'))
					->set(
						$db->quoteName('until') . ' = DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL ' . (int) $lockoutTime
						. ' SECOND)'
					)
					->where($db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')');

				$db->setQuery($extendQuery)->execute();
			}

			return true;
		}
		catch (Exception $e)
		{
			$until = null;

			return false;
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();
		}
	}

	/**
	 * Block the user's IP address.
	 *
	 * This function checks if the user's IP address is already blocked in the login lockouts table.
	 * If the IP address is already blocked, it updates the existing record by extending the lockout time.
	 * If the IP address is not blocked, it inserts a new record with the IP address and lockout time.
	 *
	 * @throws Exception  If there is an error executing the database queries.
	 *
	 * @since  1.2.0
	 */
	public function blockIp(): void
	{
		if (!$this->isAvailable())
		{
			return;
		}

		// Is the feature enabled?
		if (!((bool) $this->getContainer()->appConfig->get('login_failure_enable', 1)))
		{
			return;
		}

		// Make sure we have a current IP
		$ip = Ip::getUserIP();

		if (empty($ip))
		{
			return;
		}

		$appConfig   = $this->getContainer()->appConfig;
		$lockoutTime = max(0, $appConfig->get('login_lockout', 900));

		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__login_lockouts');

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__login_lockouts')
			->where($db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')');

		try
		{
			$numRecords = $db->setQuery($query)->loadResult();

			if ($numRecords)
			{
				// Update an existing record
				$extendQuery = $db->getQuery(true)
					->update($db->quoteName('#__login_lockouts'))
					->set(
						$db->quoteName('until') . ' = DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL ' . (int) $lockoutTime
						. ' SECOND)'
					)
					->where($db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')');

				$db->setQuery($extendQuery)->execute();
			}
			else
			{
				// Insert a record
				$insertQuery = $db->getQuery(true)
					->insert($db->quoteName('#__login_lockouts'))
					->columns(
						[
							$db->quoteName('ip'),
							$db->quoteName('until'),
						]
					)
					->values(
						'INET6_ATON(' . $db->quote($ip) . '), DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL '
						. (int) $lockoutTime . ' SECOND)'
					);

				$db->setQuery($insertQuery)->execute();
			}
		}
		catch (Exception $e)
		{
			return;
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();
		}
	}

	/**
	 * Should this login attempt be blocked?
	 *
	 * Returns true if the specified IP address has reached or exceeded the configured number of failed login attempts
	 * within the configured period.
	 *
	 * @return  bool
	 * @since   1.2.0
	 */
	public function mustBeBlocked(): bool
	{
		if (!$this->isAvailable())
		{
			return false;
		}

		// Is the feature enabled?
		if (!((bool) $this->getContainer()->appConfig->get('login_failure_enable', 1)))
		{
			return false;
		}

		// Make sure we have a current IP
		$ip = Ip::getUserIP();

		if (empty($ip))
		{
			return false;
		}

		// Get limits from application configuration
		$appConfig          = $this->getContainer()->appConfig;
		$maxAllowedFailures = max(0, $appConfig->get('login_max_failures', 5));
		$maxSeconds         = max(1, $appConfig->get('login_failure_window', 60));

		// Lock the table to avoid deadlocks and stale data
		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__login_failures');

		// Get the number of failed login attempts within the specified time
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__login_failures'))
			->where(
				[
					$db->quoteName('ip') . ' = INET6_ATON(' . $db->quote($ip) . ')',
					$db->quoteName('mark') . ' >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL ' . intval($maxSeconds)
					. ' SECOND)',
					$db->quoteName('mark') . ' <= CURRENT_TIMESTAMP()',
				]
			);

		try
		{
			$failuresInWindow = $db->setQuery($query)->loadResult();
		}
		catch (\Throwable $e)
		{
			echo $e->getMessage();

			return false;
		}
		finally
		{
			// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();
		}

		return $failuresInWindow >= $maxAllowedFailures;
	}

	private function    isAvailable(): bool
	{
		if (self::$isAvailable !== null)
		{
			return self::$isAvailable;
		}

		try
		{
			$db     = $this->container->db;
			$query  = 'SHOW TABLES LIKE ' . $db->quote('#__login_failures');
			$tables = $db->setQuery($query)->loadColumn();

			self::$isAvailable = !empty($tables);
		}
		catch (\Throwable $e)
		{
			self::$isAvailable = false;
		}

		return self::$isAvailable;
	}

	/**
	 * Convert a MySQL binary IP address to a printable string (Network to Printable).
	 *
	 * @param   string  $ip  Binary IP address, as returned by MySQL's INET6_ATON()
	 *
	 * @return  string|null  Null if invalid
	 * @since   1.2.0
	 */
	private function ip_ntop(string $ip): ?string
	{
		$length = strlen($ip);

		if (!in_array($length, [4, 16]))
		{
			return null;
		}

		$format = sprintf("A%d", $length);
		$packed = pack($format, $ip);

		if ($packed === false)
		{
			return null;
		}

		$ip = inet_ntop($packed);

		if ($ip === false)
		{
			return null;
		}

		return $ip;
	}

	/**
	 * Convert a printable IP address to a MySQL binary string (Printable to Network).
	 *
	 * @param   string  $ip  Printable IP address
	 *
	 * @return  string|null  Null if invalid
	 * @since   1.2.0
	 */
	private function ip_pton(string $ip): ?string
	{
		$packed = inet_pton($ip);

		return $packed === false ? null : $packed;
	}
}