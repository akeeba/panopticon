<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Timer\Timer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		if ($this->getTasksPausedFlag())
		{
			return Command::SUCCESS;
		}

		$container = Factory::getContainer();

		// Mark our last execution time
		$db = $container->db;
		$db->lockTable('#__akeeba_common');
		$query = $db
			->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->values(implode(',', [
				$db->quote('panopticon.task.last.execution'),
				$db->quote($container->dateFactory()->toSql()),
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
		$model = $container->mvcFactory->makeTempModel('Task');

		$timer = new Timer(
			$container->appConfig->get('max_execution', 60),
			$container->appConfig->get('execution_bias', 75)
		);

		$logger = new ForkedLogger([
			$this->getConsoleLogger($output),
			$container->loggerFactory->get('task_runner'),
		]);

		$loop = (bool) $input->getOption('loop') ?? false;

		while ($timer->getTimeLeft() > 0.01)
		{
			if ($this->getTasksPausedFlag())
			{
				$logger->info('Tasks are paused; I will not look for further tasks.');
				break;
			}

			$didRunATask = $model->runNextTask($logger, $this->ioStyle);

			// I didn't run a task. If I'm not looping, I have nothing more to do.
			if (!$didRunATask && !$loop)
			{
				break;
			}

			// I ran a task. Can I run some more?
			if ($didRunATask)
			{
				// Not enough time? Time to go away.
				if ($timer->getTimeLeft() < 5)
				{
					$logger->debug('Not enough time left; exiting');

					break;
				}

				// Yes, I have enough time to run more tasks. Let's proceed.
				continue;
			}

			// If I am here, I did not run a task and I was told to loop. But do I have enough time to do that?
			if ($timer->getTimeLeft() < 5)
			{
				$logger->debug('Not enough time left; exiting');

				break;
			}

			$logger->debug(sprintf(
				'No tasks; waiting for 5 seconds. Time left before exiting: %0.1fs',
				$timer->getTimeLeft()
			));

			sleep(5);
		}

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addOption('loop', 'l', InputOption::VALUE_NEGATABLE, 'Enter a wait loop if no tasks exist?', false);
	}
}