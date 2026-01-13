<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Tasks;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'task:list',
	description: 'List scheduled tasks',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TaskList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Tasks $model */
		$model = $container->mvcFactory->makeTempModel('Tasks');

		// Apply filters
		$search = $input->getOption('search');

		if ($search !== null)
		{
			$model->setState('search', $search);
		}

		// Get the items
		$items = $model
			->get(true)
			->map(
				fn(Tasks $x) => [
					'id'              => $x->id,
					'site_id'         => $x->site_id,
					'type'            => $x->type,
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
				'search', 's', InputOption::VALUE_OPTIONAL,
				'Search among task types'
			);
	}

}
