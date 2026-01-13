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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'scanner:schedule:list',
	description: 'List PHP File Change Scanner schedules',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ScannerScheduleList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Scannerschedules $model */
		$model = $container->mvcFactory->makeTempModel('Scannerschedules');

		// Apply filters
		$siteId = $input->getOption('site-id');

		if ($siteId !== null)
		{
			$model->setState('site_id', $siteId);
		}

		// Get the items
		$items = $model
			->get(true)
			->map(
				fn(Scannerschedules $x) => [
					'id'              => $x->id,
					'site_id'         => $x->site_id,
					'enabled'         => $x->enabled ? 'Yes' : 'No',
					'last_exit_code'  => $x->last_exit_code,
					'last_execution'  => $x->last_execution,
					'next_execution'  => $x->next_execution,
					'times_executed'  => $x->times_executed,
					'times_failed'    => $x->times_failed,
					'priority'        => $x->priority,
				]
			);

		$this->printFormattedArray(
			$items->toArray(),
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			)
			->addOption(
				'site-id', 's', InputOption::VALUE_OPTIONAL,
				'Filter by site ID'
			);
	}

}
