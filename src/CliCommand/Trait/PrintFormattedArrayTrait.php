<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

defined('AKEEBA') || die;

trait PrintFormattedArrayTrait
{
	/**
	 * Prints the array formatted with the specific format and returns an integer result
	 *
	 * @param   array|null  $data    The data to format and print
	 * @param   string      $format  One of table, json, yaml, csv, count
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function printFormattedArray(?array $data, string $format): void
	{
		$data = $data ?: [];

		$keys          = array_keys($data);
		$isNumericKeys = array_reduce($keys, fn(bool $carry, mixed $item) => $carry && is_integer($item), true);

		switch ($format)
		{
			default:
			case 'table':
				if ($isNumericKeys)
				{
					$firstRow = reset($data);
					$firstRow = is_array($firstRow) ? $firstRow : (array)$firstRow;
					$this->ioStyle->table(array_keys($firstRow), $data);

					return;
				}

				$this->ioStyle->table(['Key', 'Value'], $this->reframeData($data));
				break;

			case 'json':
				$encoders    = [new JsonEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				$this->ioStyle->writeln(
					$serializer->serialize(
						$data,
						'json',
						[
							'json_encode_options' => JSON_PRETTY_PRINT
						]
					)
				);
				break;

			case 'yaml':
				$encoders    = [new YamlEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				$this->ioStyle->writeln(
					$serializer->serialize(
						$data,
						'yaml',
						[
							'yaml_inline' => 4
						]
					)
				);

				//$this->ioStyle->writeln(Yaml::dump($data));
				break;

			case 'csv':
				$encoders    = [new CsvEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				if ($isNumericKeys)
				{
					$this->ioStyle->writeln(
						$serializer->serialize(
							$data,
							'csv'
						)
					);

					return;
				}

				$this->ioStyle->writeln(
					$serializer->serialize(
						array_merge(
							[['Key', 'Value']],
							$this->reframeData($data)
						),
						'csv'
					)
				);
				break;

			case 'count':
				$this->ioStyle->writeln(count($data));
				break;
		}
	}

	/**
	 * Prints a formatted scalar value.
	 *
	 * If the value is not a scalar, it is cast to array and printed with the printFormattedArray() method.
	 *
	 * @param   mixed   $value  The value to print
	 * @param   string  $format The format to use for printing it
	 *
	 * @return  void
	 * @see     self::printFormattedArray
	 *
	 * @since   1.0.5
	 */
	private function printFormattedScalar(mixed $value, string $format)
	{
		if (!is_scalar($value))
		{
			$this->printFormattedArray((array) $value, $format);

			return;
		}

		switch ($format)
		{
			default:
			case 'table':
				$this->ioStyle->table(['Value'], [[$value]]);
				break;

			case 'csv':
				$encoders    = [new CsvEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				$this->ioStyle->writeln($serializer->serialize(['value' => $value], 'csv', ['no_headers' => true]));
				break;

			case 'json':
				$encoders    = [new JsonEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				$this->ioStyle->writeln($serializer->serialize($value, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]));
				break;

			case 'yaml':
				$encoders    = [new YamlEncoder()];
				$normalizers = [new ObjectNormalizer()];
				$serializer  = new Serializer($normalizers, $encoders);

				$this->ioStyle->writeln($serializer->serialize($value, 'yaml', ['yaml_inline' => 4]));
				break;

			case 'count':
				$this->ioStyle->writeln('1');
				break;
		}
	}

	/**
	 * Re-frames the array data in a way that can be used for outputting a table or CSV file
	 *
	 * @param   array  $items
	 *
	 * @return  array
	 * @since   1.0.5
	 */
	private function reframeData(array $items): array
	{
		$temp = [];

		foreach ($items as $k => $v)
		{
			$temp[] = [$k, $v];
		}

		return $temp;
	}
}