<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: "site:update:joomla",
	description: "Updates Joomla! core"
)]
class SiteUpdateJoomla extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($this->getTasksPausedFlag())
		{
			return Command::SUCCESS;
		}

		/** @var LogRotateTask|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('joomlaupdate');

		if ($callback instanceof AbstractCallback)
		{
			$callback->setLogger($this->getConsoleLogger($output));
		}

		$dummy    = new \stdClass();
		$registry = new Registry();

		$dummy->site_id = $input->getArgument('id');

		if ($input->getOption('force'))
		{
			$dummy->params = new Registry();

			$dummy->params->set('force', true);
		}

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'Site ID to update')
			->addOption('force', 'f', InputOption::VALUE_NEGATABLE, 'Force the update (results in files refresh if there is no update)', false);

	}

}