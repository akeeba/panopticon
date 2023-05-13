<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;
use Complexify\Complexify;
use DateTimeZone;

class Sysconfig extends Model
{
	public function validateValue(string $key, $value): bool
	{
		$complexify = new Complexify();

		return match ($key)
		{
			// System
			'session_timeout' => filter_var($value, FILTER_VALIDATE_INT) && $value > 1,
			'timezone' => in_array($value, DateTimeZone::listIdentifiers()),
			'debug' => filter_var($value, FILTER_VALIDATE_BOOL),
			'error_reporting' => in_array($key, ['default', 'none', 'simple', 'maximum',]),
			'finished_setup' => filter_var($value, FILTER_VALIDATE_BOOL),

			// Display
			'darkmode' => filter_var($value, FILTER_VALIDATE_INT) && in_array($value, [1, 2, 3]),
			'fontsize' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 8 && $value <= 48,

			// Automation
			'webcron_key' => $complexify->evaluateSecurity($value)->valid,
			'cron_stuck_threshold' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 3,
			'max_execution' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 5 && $value <= 3600,
			'execution_bias' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 15 && $value <= 100,

			// Site Operations
			'siteinfo_freq' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 15 && $value <= 1440,

			// Caching
			'caching_time' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 1 && $value <= 527040,
			'cache_adapter' => in_array($value, ['filesystem', 'linuxfs', 'db', 'memcached', 'redis',]),
			'caching_redis_dsn' => true,
			'caching_memcached_dsn' => true,
			
			// Logging
			'log_level' => in_array($value, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']),
			'log_rotate_compress' => filter_var($value, FILTER_VALIDATE_BOOL),
			'log_rotate_files' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 0 && $value <= 100,
			'log_backup_threshold' => filter_var($value, FILTER_VALIDATE_INT) && $value >= 0 && $value <= 100,

			// Database
			'dbdriver' => in_array($value, ['mysqli', 'pdomysql']),
			'dbhost' => true,
			'dbuser' => true,
			'dbpass' => true,
			'dbname' => true,
			'dbprefix' => !empty($value) && preg_match('#^[a-zA-Z0-9_]{1,6}_$#', $value),
			'dbencryption' => filter_var($value, FILTER_VALIDATE_BOOL),
			'dbsslca' => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslkey' => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslcert' => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslverifyservercert' => filter_var($value, FILTER_VALIDATE_BOOL),

			// Anything else, we don't know what it is.
			default => false,
		};
	}
}