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
	name: 'task:joomlaupdate:director',
	description: 'Enqueue automatic updates and send update emails for Joomla sites',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TaskJoomlaUpdateDirector extends AbstractCommand
{
	use ForkedLoggerAwareTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var LogRotateTask|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('joomlaupdatedirector');

		if ($callback instanceof LoggerAwareInterface)
		{
			$callback->setLogger(
				$this->getForkedLogger(
					$output,
					[
						$container->loggerFactory->get('joomla_update_director'),
					]
				)
			);
		}

		$dummy    = new \stdClass();
		$registry = new Registry();

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;
	}
}