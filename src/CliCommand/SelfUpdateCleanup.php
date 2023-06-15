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
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Model\Selfupdate;
use Awf\Mvc\Model;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'selfupdate:cleanup',
	description: 'Cleans up after a manual update',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SelfUpdateCleanup extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		/** @var Selfupdate $model */
		$model = Model::getTmpInstance('', 'selfupdate', $container);

		$this->ioStyle->info('Finalising the update');

		$model->postUpdate();

		$this->setTasksPausedFlag(true);

		$this->ioStyle->success('The post-update clean-up is now complete');

		return Command::SUCCESS;
	}
}