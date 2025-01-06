<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
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
