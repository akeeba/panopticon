<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\DBUtils\Export;
use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'database:backup',
	description: 'Backs up the database to a SQL file',
	hidden: false,
)]
#[ConfigAssertion(true)]
class DatabaseBackup extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();

		$this->ioStyle->title('Database backup');

		$outFile  = $input->getArgument('output')
			?: sprintf("%s/db_backups/backup-%s.sql", APATH_CACHE, date('Y-m-d-His'));
		$compress = $input->getOption('compress');

		if (is_array($outFile))
		{
			$outFile = array_shift($outFile);
		}

		$this->ioStyle->info(
			sprintf(
				'Backing up database into %s',
				$outFile . ($compress ? '.gz' : '')
			)
		);

		$backupLogger = $container->loggerFactory->get('db_backup');
		$logger       = new ForkedLogger(
			[
				$this->getConsoleLogger($output),
				$backupLogger,
			]
		);

		$this->ioStyle->info('Pausing tasks');

		$this->setTasksPausedFlag(true);

		while ($this->areTasksRunning())
		{
			$this->ioStyle->writeln('Waiting for running tasks to finish running...');

			sleep(5);
		}

		$logger->debug('All tasks are paused. Starting backup.');

		$this->ioStyle->info('Starting backup');

		try
		{
			$db           = $container->db;
			$backupObject = new Export($outFile, $db);
			$backupObject->setLogger($logger);
			$backupObject->setCompress($compress);

			/** @noinspection PhpStatementHasEmptyBodyInspection */
			while ($backupObject->execute())
			{
				// Intentionally left blank
			}

			$success = true;
		}
		catch (\Throwable $e)
		{
			$success = false;
		}

		$this->ioStyle->info('Resuming tasks');

		$this->setTasksPausedFlag(false);

		if ($success)
		{
			return Command::SUCCESS;
		}

		$backupLogger->error('Backup failed');
		$backupLogger->debug(
			sprintf(
				"%s -- %d -- %s",
				get_class($e),
				$e->getCode(),
				$e->getMessage(),
			)
		);
		$backupLogger->debug(sprintf('%s:%d', $e->getFile(), $e->getLine()));
		$backupLogger->debug($e->getTraceAsString());

		$this->ioStyle->error(
			[
				'Backup failed',
				sprintf(
					"%s -- %d -- %s",
					get_class($e),
					$e->getCode(),
					$e->getMessage(),
				),
				sprintf('%s:%d', $e->getFile(), $e->getLine()),
				$e->getTraceAsString()
			]
		);

		return Command::FAILURE;
	}

	protected function configure(): void
	{
		$this
			->addArgument('output', InputOption::VALUE_OPTIONAL, 'The full path to the SQL file where the backup will be stored')
			->addOption('compress', 'c', InputOption::VALUE_NEGATABLE, 'Should I compress the file? Omit for global setting', null);
	}
}