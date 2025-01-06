<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Awf\Database\Installer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'database:update',
	description: 'Install or update the database tables',
	hidden: false,
)]
#[ConfigAssertion(true)]
class DatabaseUpdate extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		$container->db->connect();

		if (!$container->db->connected())
		{
			$this->ioStyle->error('Could not connect to database server');

			return Command::FAILURE;
		}

		$installer = new Installer($container);
		$installer->setXmlDirectory(APATH_ROOT . '/src/schema');

		if ($this->cliInput->getOption('drop'))
		{
			$this->ioStyle->info('Removing existing tables');

			$installer->removeSchema();
		}

		$this->ioStyle->info('Installing or updating the database tables');

		try
		{
			$installer->updateSchema();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error([
				'Failed to install the database tables. Error:',
				$e->getMessage()
			]);

			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this->addOption('drop', null, InputOption::VALUE_NEGATABLE, 'Drop existing tables before installing the tables?', false);
	}
}