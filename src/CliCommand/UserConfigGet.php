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
use Akeeba\Panopticon\Model\Users;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'user:config:get',
	description: 'Get the value of a user configuration variable',
	hidden: false,
)]
#[ConfigAssertion(true)]
class UserConfigGet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Users $model */
		$model = $container->mvcFactory->makeTempModel('Users');
		$id    = intval($input->getArgument('id'));

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(
				[
					sprintf('Could not find user %d', $id),
					$e->getMessage(),
				]
			);

			return Command::FAILURE;
		}

		$config = new Registry($model->parameters);

		// Output the information in the requested format
		$value = $config->get($input->getArgument('key'));

		if (is_object($value))
		{
			$value = $this->flatten((new Registry($value))->toArray());
		}

		$this->printFormattedScalar(
			$value,
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric user ID to list config values for')
			->addArgument('key', InputArgument::REQUIRED, 'The configuration key to get the value for')
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

	private function flatten(array $array, $prefix = ''): array
	{
		$ret = [];

		foreach ($array as $key => $value)
		{
			if (is_array($value))
        	{
        		foreach ($this->flatten($value, $prefix . $key . '.') as $k1 => $v1)
		        {
					$ret[$k1] = $v1;
		        }
        	}
        	else
        	{
        		$ret[$prefix . $key] = $value;
        	}
		}

		return $ret;
	}
}