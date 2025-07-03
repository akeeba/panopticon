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
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Model\Tasks;
use Akeeba\Panopticon\Model\Users;
use Awf\Utils\ArrayHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'task:list',
	description: 'List tasks',
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

		$items = $model
			->get(true);

        $siteNames = $this->populateSiteNames($items);

        $items = $items->map(
				fn(Tasks $x) => [
					'id'             => $x->id,
                    'site'           => $siteNames[$x->site_id] ?? 'System',
					'type'           => $x->type,
					'enabled'        => $x->enabled,
					'last_exit_code' => $x->last_exit_code,
                    'next_execution' => $x->next_execution
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
			);
	}

    protected function populateSiteNames($items): array
    {
        $siteNames = [];

        if (count($items) <= 0)
        {
            return $siteNames;
        }

        $siteIDs = $items->map(function (Task $task) {
            return $task->site_id;
        })->toArray();

        $siteIDs = array_unique(array_filter($siteIDs));
        $siteIDs = ArrayHelper::toInteger($siteIDs);

        if (empty($siteIDs))
        {
            return $siteNames;
        }

        $db              = Factory::getContainer()->db;
        $query           = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
            ])->from($db->quoteName('#__sites'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $siteIDs) . ')');

        return $db->setQuery($query)->loadAssocList('id', 'name') ?: [];
    }
}