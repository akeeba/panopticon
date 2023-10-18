<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

use Awf\Text\Text;

defined('AKEEBA') || die;

trait PrintFormattedArrayTrait
{
	/**
	 * Prints the array formatted with the specific format and returns an integer result
	 *
	 * @param   array|null  $data    The data to format and print
	 * @param   string      $format  One of table, json, yaml, csv, count
	 *
	 * @return  int
	 * @since   1.0.0
	 */
	private function printFormattedAndReturn(?array $data, string $format): int
	{
		if (empty($data) && ($format != 'count'))
		{
			return 0;
		}
		elseif (empty($data))
		{
			$data = [];
		}

		if (!empty($data))
		{
			$keys     = array_keys($data);
			$firstKey = array_shift($keys);
			$row      = $data[$firstKey];

			if (is_array($row))
			{
				$headers = array_keys($row);
			}
			else
			{
				$headers = array_keys($data);

				if (!in_array($format, ['json', 'yaml']))
				{
					$data = [$data];
				}
			}
		}

		switch ($format)
		{
			default:
			case 'table':
				$this->ioStyle->table($headers, $data);
				break;

			case 'json':
				$this->ioStyle->writeln(json_encode($data, JSON_PRETTY_PRINT));
				break;

			case 'yaml':
				if (!function_exists('yaml_emit'))
				{
					$line1 = Text::_('COM_ADMINTOOLS_CLI_ERR_CANNOT_GENERATE_YAML');
					$line2 = Text::_('COM_ADMINTOOLS_CLI_ERR_YAML_EXTENSION_NOT_FOUND');

					$this->ioStyle->error(<<< ERROR
$line1

$line2

ERROR

					);

					return 1;
				}

				$this->ioStyle->writeln(yaml_emit($data));
				break;

			case 'csv':
				$this->ioStyle->writeln($this->toCsv($data));
				break;

			case 'count':
				$this->ioStyle->writeln(count($data));
				break;
		}

		return 0;
	}

	/**
	 * Converts an array to its CSV representation
	 *
	 * @param   array  $data       The array data to convert to CSV
	 * @param   bool   $csvHeader  Should I print a CSV header row?
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function toCsv(array $data, bool $csvHeader = true): string
	{
		$output = '';
		$item   = array_pop($data);
		$data[] = $item;
		$keys   = array_keys($item);

		if ($csvHeader)
		{
			$csv = [];

			foreach ($keys as $k)
			{
				$k = str_replace('"', '""', $k);
				$k = str_replace("\r", '\\r', $k);
				$k = str_replace("\n", '\\n', $k);
				$k = '"' . $k . '"';

				$csv[] = $k;
			}

			$output .= implode(",", $csv) . "\r\n";
		}

		foreach ($data as $item)
		{
			$csv = [];

			foreach ($keys as $k)
			{
				$v = $item[$k];

				if (is_array($v))
				{
					$v = 'Array';
				}
				elseif (is_object($v))
				{
					$v = 'Object';
				}

				$v = str_replace('"', '""', $v);
				$v = str_replace("\r", '\\r', $v);
				$v = str_replace("\n", '\\n', $v);
				$v = '"' . $v . '"';

				$csv[] = $v;
			}

			$output .= implode(",", $csv) . "\r\n";
		}

		return $output;
	}
}