<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application;


use DateTimeZone;
use Exception;
use OutOfBoundsException;
use Psr\Log\LogLevel;
use RuntimeException;

defined('AKEEBA') || die;

trait DefaultConfigurationTrait
{
	protected static $REQUIRED = [
		'finished_setup',
		'dbdriver',
		'dbhost',
		'dbuser',
		'dbpass',
		'dbname',
		'dbprefix',
	];

	public function getDefaultConfiguration(): array
	{
		return [
			'session_timeout'          => 1440,
			'language'                 => 'en-GB',
			'timezone'                 => 'UTC',
			'debug'                    => false,
			'error_reporting'          => 'default',
			'live_site'                => '',
			'session_token_algorithm'  => 'sha512',
			'finished_setup'           => false,
			'theme'                    => 'theme',
			'darkmode'                 => 1,
			'fontsize'                 => '',
			'phpwarnings'              => true,
			'webcron_key'              => '',
			'cron_stuck_threshold'     => 3,
			'max_execution'            => 60,
			'execution_bias'           => 75,
			'siteinfo_freq'            => 60,
			'tasks_coreupdate_install' => 'patch',
			'tasks_extupdate_install'  => 'none',
			'caching_time'             => 60,
			'cache_adapter'            => 'filesystem',
			'caching_redis_dsn'        => '',
			'caching_memcached_dsn'    => '',
			'log_level'                => 'warning',
			'log_rotate_compress'      => true,
			'log_rotate_files'         => 3,
			'log_backup_threshold'     => 14,
			'dbdriver'                 => 'mysqli',
			'dbhost'                   => 'localhost',
			'dbuser'                   => '',
			'dbpass'                   => '',
			'dbname'                   => '',
			'dbprefix'                 => 'ak_',
			'dbcharset'                => 'utf8mb4',
			'dbencryption'             => false,
			'dbsslca'                  => '',
			'dbsslkey'                 => '',
			'dbsslcert'                => '',
			'dbsslverifyservercert'    => '',
			'mail_online'              => false,
			'mail_inline_images'       => false,
			'mailer'                   => 'mail',
			'mailfrom'                 => '',
			'fromname'                 => 'Panopticon',
			'smtphost'                 => 'localhost',
			'smtpport'                 => 25,
			'smtpsecure'               => 'none',
			'smtpauth'                 => false,
			'smtpuser'                 => '',
			'smtppass'                 => '',
		];
	}

	public function getConfigurationOptionAutocomplete(string $key, $currentValue): array
	{
		$values = match ($key)
		{
			'debug', 'dbencryption', 'log_rotate_compress', 'finished_setup', 'darkmode', 'phpwarnings', 'dbsslverifyservercert', 'mail_online', 'mail_inline_images', 'smtpauth' => [
				'true',
				'yes',
				'1',
				'on',
				'false',
				'no',
				'0',
				'off',
			],
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
			'default' => [],
		};

		if (empty($currentValue))
		{
			return $values;
		}

		return array_filter($values, fn($x) => stripos($x, $currentValue) === 0);
	}

	public function isValidConfigurationKey(string $key)
	{
		return array_key_exists($key, $this->getDefaultConfiguration());
	}

	private function getConfigurationOptionFilterCallback(string $key): callable
	{
		return match ($key)
		{
			'debug', 'dbencryption', 'log_rotate_compress', 'finished_setup', 'darkmode', 'phpwarnings', 'dbsslverifyservercert', 'mail_online', 'mail_inline_images', 'smtpauth' => [
				$this,
				'validateBool',
			],
			'session_timeout' => fn($x) => $this->validateInteger($x, 1440, 3, 535600),
			'log_level' => fn($x) => $this->validatePresetValues(
				$x, LogLevel::WARNING, [
					LogLevel::DEBUG,
					LogLevel::INFO,
					LogLevel::NOTICE,
					LogLevel::WARNING,
					LogLevel::ERROR,
					LogLevel::CRITICAL,
					LogLevel::ALERT,
					LogLevel::EMERGENCY,
				]
			),
			'log_backup_threshold' => fn($x) => $this->validateInteger($x, 14, 0, 100),
			'timezone' => [$this, 'validateTimezone'],
			'cron_stuck_threshold' => fn($x) => $this->validateInteger($x, 3, 3, 1440),
			'max_execution' => fn($x) => $this->validateInteger($x, 60, 10, 3600),
			'execution_bias' => fn($x) => $this->validateInteger($x, 75, 10, 100),
			'dbdriver' => fn($x) => $this->validatePresetValues($x, 'mysqli', ['mysqli', 'pdomysql']),
			'error_reporting' => fn($x) => $this->validatePresetValues(
				$x, 'default', ['default', 'none', 'simple', 'maximum']
			),
			'session_token_algorithm' => fn($x) => $this->validatePresetValues(
				$x, 'sha512', ['md5', 'sha1', 'sha224', 'sha256', 'sha384', 'sha512']
			),
			'fontsize' => fn($x) => $this->validateInteger($x, null, 8, 48),
			'siteinfo_freq' => fn($x) => $this->validateInteger($x, 60, 5, 1440),
			'tasks_coreupdate_install' => fn($x) => $this->validatePresetValues(
				$x, 'patch', ['none', 'email', 'patch', 'minor', 'major']
			),
			'tasks_extupdate_install' => fn($x) => $this->validatePresetValues(
				$x, 'none', ['none', 'email', 'patch', 'minor', 'major']
			),
			'caching_time' => fn($x) => $this->validateInteger($x, 60, 1, 1440),
			'cache_adapter' => fn($x) => $this->validatePresetValues(
				$x, 'filesystem', ['filesystem', 'linuxfs', 'db', 'memcached', 'redis',]
			),
			'log_rotate_files' => fn($x) => $this->validateInteger($x, 3, 0, 100),
			'dbcharset' => fn($x) => $this->validatePresetValues($x, 'utf8mb4', ['utf8mb4',]),
			'mailer' => fn($x) => $this->validatePresetValues($x, 'mail', ['mail', 'sendmail', 'smtp']),
			'smtpport' => fn($x) => $this->validateInteger($x, 25, 1, 65535),
			default => fn($x) => $x,
		};
	}

	private function validateBool($x): bool
	{
		if (in_array(
			$x, [
			true,
			'true',
			'on',
			'yes',
			1,
			'1',
		], true
		))
		{
			return true;
		}

		if (in_array(
			$x, [
			false,
			'false',
			'off',
			'no',
			0,
			'0',
		], true
		))
		{
			return false;
		}

		throw new RuntimeException('Not a boolean (yes/no) value.');
	}

	private function validateInteger($x, ?int $default = null, ?int $min = null, ?int $max = null): ?int
	{
		$x = is_numeric($x) ? intval($x) : $default;

		if ($default === null && $x === null)
		{
			return null;
		}

		if ($min !== null)
		{
			$x = max($min, $x);
		}

		if ($max !== null)
		{
			$x = min($max, $x);
		}

		return $x;
	}

	private function validatePresetValues($x, string $default, array $presets): string
	{
		return in_array($x, $presets) ? $x : $default;
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
}