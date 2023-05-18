<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Task;

use Akeeba\Panopticon\Helper\MaxExecutionTime;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Task\SymfonyStyleAwareInterface;
use Akeeba\Panopticon\Library\Task\SymfonyStyleAwareTrait;
use Awf\Registry\Registry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Style\SymfonyStyle;

defined('AKEEBA') || die;

#[AsTask(
	name: "maxexec",
	description: "PANOPTICON_TASKTYPE_MAXEXEC"
)]
class MaxExec extends AbstractCallback implements LoggerAwareInterface, SymfonyStyleAwareInterface
{
	use LoggerAwareTrait;
	use SymfonyStyleAwareTrait;

	private const TICK_KEY = 'maxexec.lasttick';

	private const DONE_KEY = 'maxexec.done';

	public function __invoke(object $task, Registry $storage): int
	{
		// If the last execution had timed out, return an OK so this task can be cleared.
		$lastExitCode = $task->last_exit_code ?? 0;

		if ($lastExitCode === Status::TIMEOUT || $lastExitCode === Status::TIMEOUT->value)
		{
			$this->ioStyle?->success(
				[
					"Nothing to do",
					"",
					"The last run of this script timed out, which is an expected result (it tells us",
					"at which point CLI scripts fail).",
					"",
					"We are now going to exit gracefully",
				]
			);
			$this->logger?->debug('Last execution had timed out; this task has served its purpose.');

			return Status::OK->value;
		}

		// Clear the maxexec.lasttick indicator
		$this->ioStyle->info('Clearing previous execution information');
		$this->logger?->debug('Clearing the last tick indicator in the database');

		$db    = $this->container->db;
		$db->lockTable('#__akeeba_common');
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' IN(' .
				$db->quote(self::TICK_KEY) . ', ' . $db->quote(self::DONE_KEY) . ')');
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// Just ignore exceptions...
		}
		finally
		{
			$db->unlockTables();
		}

		// Get the recommended max execution time and cap it to 185 seconds
		$this->ioStyle?->info('Getting maximum execution time information from the system');
		$this->logger?->debug('Getting maximum execution time information from the system');

		// There is no point running for under 15 seconds...
		$maxExecution = max(15, MaxExecutionTime::getBestLimit(185));

		$this->ioStyle?->writeln(sprintf('This script will run for up to %d seconds', $maxExecution));
		$this->logger?->info(sprintf('This task will run for up to %d seconds', $maxExecution));

		$this->ioStyle?->writeln([
			"<comment>We are now going to test the maximum execution time available to CLI scripts.  ",
			"This will take up to {$maxExecution} seconds to complete. You may see the script fail",
			"before it reaches 100%. This is expected! We are trying to push your server",
			"limits, to see when exactly it will fail.</comment>"
		]);

		$startTime = microtime(true);
		$execTime  = 0.0;
		$appConfig = $this->container->appConfig;

		$bar       = $this->ioStyle->createProgressBar($maxExecution);

		$bar?->setRedrawFrequency(1);
		$bar?->display();

		while (true)
		{
			$integerSeconds = (int) floor($execTime);

			if ($integerSeconds >= $maxExecution)
			{
				break;
			}

			// Mark maxexec.lasttick
			$this->logger?->debug(sprintf('Marking execution tick at %d seconds', $integerSeconds));

			$db->lockTable('#__akeeba_common');
			$query = $db->getQuery(true)
				->replace($db->quoteName('#__akeeba_common'))
				->values(
					$db->quote(self::TICK_KEY) . ',' .
					$db->quote($integerSeconds)
				);
			try
			{
				$db->setQuery($query)->execute();
			}
			catch (\Throwable)
			{
				// Just ignore exceptions...
			}
			finally
			{
				$db->unlockTables();
			}

			// Update the application configuration every 5 seconds
			if ($integerSeconds % 5 === 0)
			{
				$bestExec = max(10, $integerSeconds - 5);

				$this->logger?->debug(sprintf('Marking maximum execution as %d seconds', $bestExec));

				$appConfig->set('max_execution', $bestExec);

				try
				{
					$appConfig->saveConfiguration();
				}
				catch (\Exception $e)
				{
					$this->ioStyle?->error('Could not write application configuration.');

					break;
				}

				$bar?->setMessage(sprintf('New max execution is %d seconds', $bestExec));
			}

			// Wait for 1 second, wasting CPU time
			while (microtime(true) - $startTime < (1 + $integerSeconds)) {
				for ($i = 0; $i <= 10000; $i++)
				{
					hash_hmac('sha1', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', bin2hex(random_bytes(16)));
				}
			}

			$execTime = microtime(true) - $startTime;
			$bar?->advance();
		}

		$bar?->finish();

		$db->lockTable('#__akeeba_common');
		$query = $db->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->values(
				$db->quote(self::DONE_KEY) . ',' .
				$db->quote(1)
			);
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// Just ignore exceptions...
		}
		finally
		{
			$db->unlockTables();
		}


		$this->ioStyle?->success('We have finished testing the maximum execution time for your server.');

		return Status::OK->value;
	}
}