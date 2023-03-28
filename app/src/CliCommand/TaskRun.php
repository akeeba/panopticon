<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Task;
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
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

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

		while ($timer->getTimeLeft() > 0.01)
		{
			if (!$model->runNextTask())
			{
				break;
			}
		}

		return Command::SUCCESS;
	}

}