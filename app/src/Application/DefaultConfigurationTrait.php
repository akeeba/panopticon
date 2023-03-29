<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Application;


use DateTimeZone;
use Exception;
use OutOfBoundsException;
use Psr\Log\LogLevel;
use RangeException;
use RuntimeException;

defined('AKEEBA') || die;

trait DefaultConfigurationTrait
{
	public function getDefaultConfiguration(): array
	{
		return [
			'session_timeout' => 1440,
			'timezone'        => 'UTC',
			'debug'           => false,

			'log_level'            => 'warning',
			'log_rotate_compress'  => true,
			'log_rotate_files'     => 3,
			'log_backup_threshold' => 14,

			'cron_stuck_threshold'  => 3,
			'max_execution'         => 60,
			'execution_bias'        => 75,
			'dbdriver'              => 'mysqli',
			'dbhost'                => 'localhost',
			'dbuser'                => '',
			'dbpass'                => '',
			'dbname'                => '',
			'dbprefix'              => 'ak_',
			'dbencryption'          => false,
			'dbsslca'               => '',
			'dbsslkey'              => '',
			'dbsslcert'             => '',
			'dbsslverifyservercert' => '',
		];
	}

	public function getConfigurationOptionAutocomplete(string $key, $currentValue): array
	{
		$values = match ($key)
		{
			'debug', 'dbencryption', 'log_rotate_compress' => ['true', 'yes', '1', 'on', 'false', 'no', '0', 'off'],
			'session_timeout' => [3, 10, 15, 30, 45, 60, 90, 120, 180, 1440],
			'timezone' => DateTimeZone::listIdentifiers(),
			'log_level' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
			'log_rotate_files' => [0, 1, 2, 3, 5, 10, 20, 30, 31, 60, 90, 100],
			'log_backup_threshold' => [0, 1, 3, 5, 7, 10, 14, 30, 31, 60, 90, 180, 365, 366],
			'cron_stuck_threshold' => [3, 5, 10, 15, 30, 45, 60],
			'max_execution' => [10, 14, 20, 30, 60, 90, 180, 300, 600, 900, 1800, 3600],
			'execution_time_bias' => [10, 20, 25, 50, 60, 75, 80, 85, 90, 95, 100],
			'dbdriver' => ['mysqli', 'pdomysql'],
			'dbhost' => array_filter(['localhost', '127.0.0.1', $this->get('dbhost')]),
			'default' => []
		};

		if (empty($currentValue))
		{
			return $values;
		}

		return array_filter($values, fn($x) => stripos($x, $currentValue) === 0);
	}

	private function getConfigurationOptionFilterCallback(string $key): callable
	{
		return match ($key)
		{
			'debug', 'dbencryption', 'log_rotate_compress' => [$this, 'validateBool'],
			'session_timeout' => [$this, 'validateSessionTimeout'],
			'log_level' => [$this, 'validateLogLevel'],
			'log_rotate_files' => [$this, 'validateLogRotateFiles'],
			'log_backup_threshold' => [$this, 'validateLogBackupThreshold'],
			'timezone' => [$this, 'validateTimezone'],
			'cron_stuck_threshold' => [$this, 'validateCronStuckThreshold'],
			'max_execution' => [$this, 'validateMaxExecution'],
			'execution_bias' => [$this, 'validateExecutionBias'],
			'dbdriver' => [$this, 'validateDatabaseDriver'],
			default => fn($x) => $x,
		};
	}

	public function isValidConfigurationKey(string $key)
	{
		return array_key_exists($key, $this->getDefaultConfiguration());
	}

	private function validateBool($x): bool
	{
		if (in_array($x, [
			true, 'true', 'on', 'yes', 1, '1',
		], true))
		{
			return true;
		}

		if (in_array($x, [
			false, 'false', 'off', 'no', 0, '0',
		], true))
		{
			return false;
		}

		throw new RuntimeException('Not a boolean (yes/no) value.');
	}

	private function validateSessionTimeout($x): int
	{
		if (!is_numeric($x))
		{
			throw new RuntimeException('Not an integer');
		}

		$x = intval($x);

		if ($x < 3 || $x > 525600)
		{
			throw new RangeException('Session Timeout must be between 3 and 525600 minutes.');
		}

		return $x;
	}

	private function validateLogLevel($x): string
	{
		if (!in_array($x, [
			LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE, LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL,
			LogLevel::ALERT, LogLevel::EMERGENCY
		], true))
		{
			return LogLevel::WARNING;
		}

		return $x;
	}

	private function validateTimezone($x): string
	{
		try
		{
			$tz = new DateTimeZone($x);
		}
		catch (Exception $e)
		{
			$tz = false;
		}

		if ($tz === false)
		{
			throw new OutOfBoundsException('Invalid timezone');
		}

		return $x;
	}

	private function validateCronStuckThreshold($x): int
	{
		if (!is_numeric($x) || $x < 3 || $x > 1440)
		{
			throw new OutOfBoundsException('Not an integer between 3 and 1440');
		}

		return intval($x);
	}

	private function validateMaxExecution($x): int
	{
		if (!is_numeric($x) || $x < 10 || $x > 3600)
		{
			throw new OutOfBoundsException('Not an integer between 10 and 3600');
		}

		return intval($x);
	}

	private function validateLogRotateFiles($x): int
	{
		if (!is_numeric($x) || $x < 0 || $x > 100)
		{
			throw new OutOfBoundsException('Not an integer between 0 and 100');
		}

		return intval($x);
	}

	private function validateLogBackupThreshold($x): int
	{
		if (!is_numeric($x) || $x < 0 || $x > 65535)
		{
			throw new OutOfBoundsException('Not an integer between 0 and 65535');
		}

		return intval($x);
	}

	private function validateExecutionBias($x): int
	{
		if (!is_numeric($x) || $x < 10 || $x > 100)
		{
			throw new OutOfBoundsException('Not an integer between 10 and 100');
		}

		return intval($x);
	}

	private function validateDatabaseDriver($x): string
	{
		if (!is_string($x) || !in_array(strtolower($x), ['mysqli', 'pdomysql']))
		{
			throw new RuntimeException('Only the mysqli and pdomysql drivers are supported');
		}

		return strtolower($x);
	}
}