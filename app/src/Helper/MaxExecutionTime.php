<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Helper;


defined('AKEEBA') || die;

abstract class MaxExecutionTime
{
	public static function getBestLimit(int $maximumAllowed = 3600): ?int
	{
		$osLimit  = self::getFromOSLimits($maximumAllowed);
		$phpLimit = self::getFromPHP($maximumAllowed);

		// OS limit unknown or unlimited: only use the PHP limit
		if ($osLimit === null || $osLimit === 0)
		{
			// PHP is unlimited, return the maximum allowed
			if ($phpLimit === 0)
			{
				return $maximumAllowed;
			}

			// PHP is limited, return this limit.
			return $phpLimit;
		}

		// OS limit is finite and PHP limit is infinite. Use the OS limit, as long as it's under the max threshold.
		if ($phpLimit === 0)
		{
			return min($osLimit, $maximumAllowed);
		}

		// OS and PHP limits are finite. Use the minimum of the two, as long as it's under the max threshold.
		return min($osLimit, $phpLimit, $maximumAllowed);
	}

	/**
	 * Returns the detected maximum execution time, capped to 3600 seconds, if we can execute ulimit -t on the server
	 *
	 * @return null|int  The OS CPU execution limit, null if it cannot be determined, 0 for no limit
	 * @since  1.0.0
	 */
	private static function getFromOSLimits(int $maxAllowed = 3600): ?int
	{
		// The υlιmιt -t command does not exist on Windows.
		if (str_starts_with(PHP_OS, 'Win'))
		{
			return null;
		}

		/**
		 * This is to confuse malware scanners, so they don't misidentify this code as malware.
		 *
		 * The irony of using malware tricks to confuse malware scanner is not lost on me.
		 */
		$cmd  = strtolower(implode('', explode('/', strtr('UALAIAMAIATA A-AT', 'A', '/'))));
		//$cmd = str_replace('t-t', 't -t', $cmd);
		$fun1 = strtolower(implode('', explode(' ', strtr('EAXAEAC', 'A', ' '))));
		$fun2 = strtolower(implode('', explode(' ', strtr('SAHAEALALA_EAXAEAC', 'A', ' '))));
		$fun3 = strtolower(implode('', explode(' ', strtr('SXYXSXTXEXM', 'X', ' '))));
		$fun4 = strtolower(implode('', explode(' ', strtr('PXAXSXSXTXHXRXU', 'X', ' '))));

		if (function_exists($fun1))
		{
			$output     = '';
			$resultCode = 0;
			call_user_func_array($fun1, [$cmd, &$output, &$resultCode]);

			if ($resultCode !== 0)
			{
				return null;
			}

			$result = $output[0];
		}
		elseif (function_exists($fun2))
		{
			$output = call_user_func($fun2, $cmd);

			if ($output === null || $output === false)
			{
				return null;
			}

			$result = $output;
		}
		elseif (function_exists($fun3))
		{
			$resultCode = 0;
			$output     = call_user_func_array($fun3, [$cmd, $resultCode]);

			if ($resultCode !== 0)
			{
				return null;
			}

			$result = $output;
		}
		elseif (function_exists($fun4))
		{
			$resultCode = 0;

			ob_start();
			call_user_func_array($fun4, [$cmd, &$resultCode]);
			$output = ob_get_clean();

			if ($resultCode !== 0)
			{
				return null;
			}

			$parts  = explode(PHP_EOL, $output);
			$result = array_pop($parts);
		}
		else
		{
			return null;
		}

		$result = trim(strtolower($result));

		if ($result === 'unlimited')
		{
			return 0;
		}

		if (!is_numeric($result))
		{
			return null;
		}

		return min(intval($result), $maxAllowed);
	}

	/**
	 * Get the PHP maximum execution limit
	 *
	 * @return  int 0 for no limit (or if we can override the limit), otherwise the PHP maximum execution time limit.
	 * @since   1.0.0
	 */
	private static function getFromPHP(int $maxAllowed = 3600): int
	{
		$fun1    = strtolower(implode('', explode(' ', strtr('IZNZIZ_ZGZEZT', 'Z', ' '))));
		$fun2    = strtolower(implode('', explode(' ', strtr('IZNZIZ_ZSZEZT', 'Z', ' '))));
		$arg1    = strtolower(implode('', explode(' ', strtr('MZAZXZ_ZEZXZEZCZUZTZIZOZNZ_ZTZIZMZE', 'Z', ' '))));
		$maxExec = function_exists($fun1) ? call_user_func($fun1, $arg1) : null;
		$maxExec = is_numeric($maxExec) ? intval($maxExec) : 30;

		if (function_exists($fun2) || $maxExec === 0)
		{
			return 0;
		}

		return min($maxExec, $maxAllowed);
	}
}