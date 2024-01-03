<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;


defined('AKEEBA') || die;

trait FormatFilesizeTrait
{
	/**
	 * Converts number of bytes to a human-readable representation.
	 *
	 * @param   int|null  $sizeInBytes         Size in bytes
	 * @param   int       $decimals            How many decimals should I use? Default: 2
	 * @param   string    $decSeparator        Decimal separator
	 * @param   string    $thousandsSeparator  Thousands grouping character
	 *
	 * @return  string
	 * @since   1.0.3
	 */
	protected function formatFilesize(
		?int $sizeInBytes, int $decimals = 2, string $decSeparator = '.', string $thousandsSeparator = ''
	): string
	{
		if ($sizeInBytes <= 0)
		{
			return '&mdash;';
		}

		$units = ['b', 'KiB', 'MiB', 'GiB', 'TiB'];
		$unit  = floor(log($sizeInBytes, 2) / 10);

		if ($unit == 0)
		{
			$decimals = 0;
		}

		return number_format($sizeInBytes / (1024 ** $unit), $decimals, $decSeparator, $thousandsSeparator) . ' '
		       . $units[$unit];
	}

}