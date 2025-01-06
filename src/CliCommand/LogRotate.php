<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Task\LogRotate as LogRotateTask;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: "log:rotate",
	description: "Rotate log files"
)]
class LogRotate extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		if ($this->getTasksPausedFlag())
		{
			return Command::SUCCESS;
		}

		/** @var LogRotateTask|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('logrotate');

		if ($callback instanceof AbstractCallback)
		{
			$callback->setLogger($this->getConsoleLogger($output));
		}

		$dummy1 = new \stdClass();
		$dummy2 = new Registry();

		do {
			$return = $callback($dummy1, $dummy2);
		} while ($return === Status::WILL_RESUME);

		return Command::SUCCESS;
	}

}