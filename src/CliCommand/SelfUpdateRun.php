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
use Akeeba\Panopticon\Model\Selfupdate;
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
	name: 'selfupdate:run',
	description: 'Updates Panopticon',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SelfUpdateRun extends AbstractCommand
{
	use ConsoleLoggerTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		/** @var Selfupdate $model */
		$model = Model::getTmpInstance('', 'selfupdate', $container);

		$force = $input->getOption('force');

		$updateInformation = $model->getLatestVersion($force);

		if (empty($updateInformation))
		{
			$this->ioStyle->error('Cannot retrieve the update information');

			return Command::FAILURE;
		}

		$currentVersion = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev';
		$newVersion = $updateInformation->version;
		$hasUpdate = version_compare($newVersion, $currentVersion, 'gt');

		if (!$hasUpdate)
		{
			$this->ioStyle->note(
				sprintf('You already have the latest version, %s', $currentVersion)
			);

			return Command::SUCCESS;
		}

		$this->ioStyle->info(sprintf('Downloading version %s', $newVersion));

		$tempFile = $model->download();

		$this->ioStyle->text(sprintf('Downloaded into %s', $tempFile));

		$this->ioStyle->info('Extracting the update');

		$model->extract($tempFile);

		$this->ioStyle->info('Finalising the update');

		$model->postUpdate();

		$this->ioStyle->success(sprintf('Panopticon upgraded to version %s', $newVersion));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('force', 'f', InputOption::VALUE_NEGATABLE, 'Force reload the updates', false);

	}
}