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
	name: 'site:backup',
	description: 'Take a backup of a site using Akeeba Backup',
	hidden: false,
)]
#[ConfigAssertion(false)]
class SiteBackup extends AbstractCommand
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
		$container->appConfig->loadConfiguration();

		$callback  = $container->taskRegistry->get('akeebabackup');

		if ($callback instanceof AbstractCallback)
		{
			$callback->setLogger($this->getConsoleLogger($output));
		}

		$params  = new Registry();
		$storage = new Registry();

		$profile     = $input->getOption('profile');
		$description = $input->getOption('description');

		if (!empty($profile))
		{
			$params->set('profile_id', $profile);
		}

		if (!empty($description))
		{
			$params->set('description', $description);
		}

		$dummyTask          = new \stdClass();
		$dummyTask->site_id = $input->getArgument('site');
		$dummyTask->params  = $params;
		$dummyTask->storage = $storage;

		do
		{
			$return = $callback($dummyTask, $storage);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;

	}

	protected function configure(): void
	{
		$this
			->addArgument('site', InputOption::VALUE_REQUIRED, 'The numeric ID of the site which will be backed up.')
			->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'The numeric profile ID used for the backup.', 1)
			->addOption('description', null, InputOption::VALUE_OPTIONAL, 'The description of the backup', null);
	}
}