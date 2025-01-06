<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Model\Selfupdate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'selfupdate:run',
	description: 'Updates Panopticon',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SelfUpdateRun extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		/** @var Selfupdate $model */
		$model = $container->mvcFactory->makeTempModel('selfupdate');

		$force = $input->getOption('force');

		$updateInformation = $model->getLatestVersion($force);

		if (empty($updateInformation))
		{
			$this->ioStyle->error('Cannot retrieve the update information');

			return Command::FAILURE;
		}

		$currentVersion = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev';
		$newVersion     = $updateInformation->version;
		$hasUpdate      = version_compare($newVersion, $currentVersion, 'gt');

		if (!$hasUpdate)
		{
			$this->ioStyle->note(
				sprintf('You already have the latest version, %s', $currentVersion)
			);

			return Command::SUCCESS;
		}

		if (defined('APATH_IN_DOCKER') && APATH_IN_DOCKER)
		{
			$this->ioStyle->error([
				'You are running Panopticon inside Docker.',
				'You must NOT use Panopticon\'s self-update for this configuration. Please read',
				'https://github.com/akeeba/panopticon/wiki/Using-Docker#updates'
			]);

			return Command::FAILURE;
		}

		$this->ioStyle->info(sprintf('Downloading version %s', $newVersion));

		$tempFile = $model->download();

		$this->ioStyle->text(sprintf('Downloaded into %s', $tempFile));

		$this->setTasksPausedFlag(true);

		try
		{
			do
			{
				$this->ioStyle->info('Waiting for running tasks to finish running...');

				sleep(5);
			} while ($this->areTasksRunning());

			$this->ioStyle->info('Extracting the update');

			$model->extract($tempFile);
			$model->invalidatePHPFiles($tempFile);
			$model->clearCompiledTemplates();

			$this->ioStyle->info('Finalising the update');

			$model->postUpdate();
		}
		catch (\Throwable $e)
		{
			$this->setTasksPausedFlag(false);

			throw $e;
		}

		$this->ioStyle->success(sprintf('Panopticon upgraded to version %s', $newVersion));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('force', 'f', InputOption::VALUE_NEGATABLE, 'Force reload the updates', false);
	}
}