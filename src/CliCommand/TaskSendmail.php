<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\ForkedLoggerAwareTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\LogRotate as LogRotateTask;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Awf\Timer\Timer;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'task:sendmail',
	description: 'Sends enqueued email messages',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TaskSendmail extends AbstractCommand
{
	use ForkedLoggerAwareTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var LogRotateTask|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('sendmail');

		if ($callback instanceof LoggerAwareInterface)
		{
			$callback->setLogger(
				$this->getForkedLogger(
					$output,
					[
						$container->loggerFactory->get('sendmail'),
					]
				)
			);
		}

		$dummy1 = new \stdClass();
		$dummy2 = new Registry();

		do {
			$return = $callback($dummy1, $dummy2);
		} while ($return === Status::WILL_RESUME);

		return Command::SUCCESS;
	}
}