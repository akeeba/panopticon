<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
	name: 'config:set',
	description: 'Set a configuration value',
	hidden: false,
)]
class ConfigSet extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$appConfig = Factory::getContainer()->appConfig;

		$key   = $input->getArgument('key');
		$value = $input->getArgument('value');

		if (!$appConfig->isValidConfigurationKey($key))
		{
			$this->ioStyle->error(
				sprintf('Invalid configuration key ‘%s’', $key)
			);

			return Command::FAILURE;
		}

		try
		{
			$appConfig->set($key, $value);
		}
		catch (Throwable $e)
		{
			$this->ioStyle->error(
				[
					"Cannot set configuration variable ‘{$key}’",
					$e->getMessage()
				]
			);

			return Command::FAILURE;
		}

		try
		{
			$appConfig->saveConfiguration();
		}
		catch (Throwable $e)
		{
			$this->ioStyle->error(
				sprintf(
					'Could not save configuration to %s',
					$appConfig->getDefaultPath()
				)
			);

			return Command::FAILURE;
		}

		$this->ioStyle->success(
			sprintf('Set config key ‘%s’ to ‘%s’.', $key, $value)
		);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addArgument(
				'key',
				InputArgument::REQUIRED,
				'The configuration key to set',
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
			->addArgument('value', InputArgument::REQUIRED, 'Value to set', null);
	}


}