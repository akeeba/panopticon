<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

@error_reporting(E_ALL & ~E_DEPRECATED);

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Scannerschedules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'scanner:schedule:set',
	description: 'Update an existing PHP File Change Scanner schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ScannerScheduleSet extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Scannerschedules $model */
		$model = $container->mvcFactory->makeTempModel('Scannerschedules');

		$id = intval($input->getArgument('id'));

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(sprintf('Could not find scanner schedule %d', $id));

			return Command::FAILURE;
		}

		$cron = $input->getOption('cron');

		if ($cron !== null)
		{
			$model->cron_expression = $cron;
		}

		try
		{
			$model->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to save scanner schedule: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully updated scanner schedule %d', $id));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputOption::VALUE_REQUIRED, 'The numeric ID of the scanner schedule')
			->addOption('cron', null, InputOption::VALUE_REQUIRED, 'CRON expression');
	}

}
