<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'task:joomlaupdate:director',
	description: 'Enqueue automatic core updates and send update emails for Joomla! sites',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TaskJoomlaUpdateDirector extends AbstractCommand
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
		$callback  = $container->taskRegistry->get('joomlaupdatedirector');

		if ($callback instanceof AbstractCallback)
		{
			$callback->setLogger($this->getConsoleLogger($output));
		}

		$dummy    = new \stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', $input->getOption('batchSize'));
		$registry->set('force', $input->getOption('force'));
		$registry->set('force_queue', $input->getOption('force_queue'));
		$registry->set('filter.ids', $input->getOption('id'));

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('force', 'f', InputOption::VALUE_NEGATABLE, 'Force processing sites, regardless of when we last did that', false)
			->addOption('force_queue', null, InputOption::VALUE_NEGATABLE, 'Always enqueue sites for updates, even when it is not necessary', false)
			->addOption('batchSize', null, InputOption::VALUE_OPTIONAL, 'Number of sites to process at once', 10)
			->addOption('id', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Site IDs to update. Omit for all.', []);
	}

}