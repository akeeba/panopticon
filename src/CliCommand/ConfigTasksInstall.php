<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Setup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'config:tasks:install',
	description: 'Installs or re-installs the default system tasks',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ConfigTasksInstall extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		/**
		 * @var  Setup $model The Task model.
		 *
		 * IMPORTANT! We deliberately use the PHP 5.x / 7.x calling convention.
		 *
		 * Using the PHP 8.x and later calling convention with named parameters does not allow graceful termination on older
		 * PHP versions.
		 */
		$model = $container->mvcFactory->makeTempModel('Setup');

		$model->installDefaultTasks();

		return Command::SUCCESS;
	}
}