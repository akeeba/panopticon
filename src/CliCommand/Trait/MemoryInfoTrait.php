<?php
/**
 * @package   akeebabackup
 * @copyright Copyright (c)2006-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

defined('AKEEBA') || die;

trait MemoryInfoTrait
{
	private function getMemoryUsage(): string
	{
		if (!function_exists('memory_get_usage'))
		{
			return "(unknown)";
		}

		$size = memory_get_usage();
		$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

		return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
	}

	private function getPeakMemoryUsage(): string
	{
		if (!function_exists('memory_get_peak_usage'))
		{
			return "(unknown)";
		}

		$size = memory_get_peak_usage();
		$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

		return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
	}
}
