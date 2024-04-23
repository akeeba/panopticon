<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'config:list',
	description: 'List configuration variables',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ConfigList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		$ret       = [];

		foreach ($container->appConfig->toArray() as $k => $v)
		{
			// Skip some keys
			if (in_array($k, ['user_class']))
			{
				continue;
			}

			// Set up basic information: key and value
			$item['key']   = $k;
			$item['value'] = $v;

			// Figure out the human-radable description to print out
			$langKey             = strtoupper('PANOPTICON_SYSCONFIG_LBL_FIELD_' . $k);
			$langString          = match ($k)
			{
				'prefix' => $container->language->text('PANOPTICON_SETUP_LBL_DATABASE_PREFIX'),
				'dbsslcipher' => $container->language->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCIPHER'),
				default => $container->language->text($langKey),
			};
			$item['description'] = $langString === $langKey ? '' : $langString;

			$ret[] = $item;
		}

		// Output the information in the requested format
		$this->printFormattedArray(
			$ret,
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

}