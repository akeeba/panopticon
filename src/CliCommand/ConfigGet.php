<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
	name: 'config:get',
	description: 'Get a configuration value',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ConfigGet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$appConfig = Factory::getContainer()->appConfig;

		$key   = $input->getArgument('key');

		if (!$appConfig->isValidConfigurationKey($key))
		{
			$this->ioStyle->error(
				sprintf('Invalid configuration key ‘%s’', $key)
			);

			return Command::FAILURE;
		}

		try
		{
			$value = $appConfig->get($key);
		}
		catch (Throwable $e)
		{
			$this->ioStyle->error(
				[
					"Cannot get configuration variable ‘{$key}’",
					$e->getMessage()
				]
			);

			return Command::FAILURE;
		}

		$this->printFormattedScalar(
			$value,
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addArgument(
				'key',
				InputArgument::REQUIRED,
				'The configuration key to get',
				null,
				function (CompletionInput $input) {
					$currentValue = $input->getCompletionValue();

					$appConfig = Factory::getContainer()->appConfig;
					$keys      = array_keys($appConfig->getDefaultConfiguration());

					if (empty($currentValue))
					{
						return $keys;
					}

					return array_filter(
						$keys,
						fn($key) => stripos($key, $currentValue) === 0
					);
				}
			)
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

}