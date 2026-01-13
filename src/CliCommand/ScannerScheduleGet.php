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
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Scannerschedules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'scanner:schedule:get',
	description: 'Get details of a PHP File Change Scanner schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ScannerScheduleGet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Scannerschedules $model */
		$model = $container->mvcFactory->makeTempModel('Scannerschedules');

		$id = $input->getArgument('id');

		try
		{
			$model->findOrFail((int) $id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(sprintf('Could not find scanner schedule %d', $id));

			return Command::FAILURE;
		}

		$data = [
			'id'              => $model->id,
			'site_id'         => $model->site_id,
			'enabled'         => $model->enabled ? 'Yes' : 'No',
			'cron_expression' => (string)$model->cron_expression,
			'params'          => $model->getParams()->toArray(),
			'last_exit_code'  => $model->last_exit_code,
			'last_execution'  => $model->last_execution,
			'last_run_end'    => $model->last_run_end,
			'next_execution'  => $model->next_execution,
			'times_executed'  => $model->times_executed,
			'times_failed'    => $model->times_failed,
			'priority'        => $model->priority,
		];

		$this->printFormattedArray(
			$data,
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric ID of the scanner schedule')
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

}
