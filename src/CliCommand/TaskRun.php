<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Timer\Timer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'task:run',
	description: 'Runs scheduled tasks',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TaskRun extends AbstractCommand
{
	use ConsoleLoggerTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		// Mark our last execution time
		$db = $container->db;
		$db->lockTable('#__akeeba_common');
		$query = $db
			->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->values(implode(',', [
				$db->quote('panopticon.task.last.execution'),
				$db->quote((new Date())->toSql()),
			]));
		$db->setQuery($query)->execute();
		$db->unlockTables();

		/**
		 * @var  Task $model The Task model.
		 *
		 * IMPORTANT! We deliberately use the PHP 5.x / 7.x calling convention.
		 *
		 * Using the PHP 8.x and later calling convention with named parameters does not allow graceful termination on older
		 * PHP versions.
		 */
		$model = Model::getTmpInstance('', 'Task', $container);

		$timer = new Timer(
			$container->appConfig->get('max_execution', 60),
			$container->appConfig->get('execution_bias', 75)
		);

		$logger = new ForkedLogger([
			$this->getConsoleLogger($output),
			$container->loggerFactory->get('task_runner'),
		]);

		while ($timer->getTimeLeft() > 0.01)
		{
			if (!$model->runNextTask($logger, $this->ioStyle))
			{
				break;
			}
		}

		return Command::SUCCESS;
	}
}