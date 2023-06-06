<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

use Akeeba\Panopticon\Exception\InvalidTaskType;
use Akeeba\Panopticon\Helper\TaskUtils;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Task\SymfonyStyleAwareInterface;
use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Cron\CronExpression;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

defined('AKEEBA') || die;

/**
 * Handles the task queue
 *
 * @property int            $id              Task ID
 * @property int            $site_id         The site the task belongs to, 0 = system task
 * @property string         $type            Task type
 * @property Registry       $params          Task parameters
 * @property Registry       $storage         Transient data storage
 * @property CronExpression $cron_expression The CRON expression for task execution
 * @property bool           $enabled         Is it enabled?
 * @property Status         $last_exit_code  Last execution's exit code
 * @property Date           $last_execution  Last execution started date and time (UTC)
 * @property Date           $last_run_end    Last execution ended date and time (UTC)
 * @property Date|null      $next_execution  Next execution date and time (UTC)
 * @property int            $times_executed  How many times this task has executed
 * @property int            $times_failed    How many times this task has failed
 * @property Date|null      $locked          Date and time the task was locked
 * @property int            $priority
 *
 * @noinspection PhpUnused
 */
class Task extends DataModel
{
	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__tasks';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		return $query;
	}


	public function check(): self
	{
		parent::check();

		/**
		 * If this is a brand new CRON job set the last execution task to the previous run start relative to the current
		 * date and time. This will prevent the CRON job from starting immediately after scheduling it.
		 */
		if ($this->last_execution === null)
		{
			try
			{
				$cron_expression      = $this->cron_expression instanceof CronExpression
					? $this->cron_expression
					: new CronExpression($this->cron_expression);
				$previousRun          = $cron_expression->getPreviousRunDate()->format(DATE_W3C);
				$this->last_execution = (new Date($previousRun, 'UTC'))->toSql();
			}
			catch (Exception)
			{
				$this->last_execution = (new Date('2000-01-01 00:00:00', 'UTC'))->toSql();
			}

			$this->last_run_end   = null;
			$this->last_exit_code = Status::INITIAL_SCHEDULE->value;
		}

		return $this;
	}

	public function runNextTask(?LoggerInterface $logger = null, ?SymfonyStyle $ioStyle = null): bool
	{
		$logger ??= new NullLogger();
		$db     = $this->getDbo();

		@ob_start();

		$logger->info('Locking task tables');

		// Lock the table to avoid concurrency issues
		try
		{
			$query = 'LOCK TABLES ' . $db->quoteName($this->tableName) . ' WRITE, '
				. $db->quoteName($this->tableName, 's') . ' WRITE, '
				. $db->quoteName('#__sites') . ' WRITE';
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			$logger->error(
				sprintf(
					'Locking task tables failed [%s:%d]: %s',
					$e->getFile(),
					$e->getLine(),
					$e->getMessage()
				)
			);

			ob_end_clean();

			return false;
		}

		// Cancel any tasks which appear to be stuck
		try
		{
			$logger->info('Cleaning up stuck tasks');

			$this->cleanUpStuckTasks();
		}
		catch (Throwable $e)
		{
			$logger->error(
				sprintf(
					'Failed to clean up stuck tasks [%s:%d]: %s',
					$e->getFile(),
					$e->getLine(),
					$e->getMessage()
				)
			);

			// If an error occurred it means that a past lock has not yet been freed; give up.
			$db->unlockTables();

			@ob_end_clean();

			return false;
		}

		// Get the next pending task
		try
		{
			$logger->info('Getting next task');

			$pendingTask = $this->getNextTask();

			if (empty($pendingTask))
			{
				$logger->info('There are no pending tasks.');

				$db->unlockTables();

				return false;
			}
		}
		catch (Exception $e)
		{
			$logger->error(
				sprintf(
					'Task retrieval failed %s:%d: %s',
					$e->getFile(),
					$e->getLine(),
					$e->getMessage()
				)
			);

			$db->unlockTables();

			@ob_end_clean();

			return false;
		}

		// Log the task (System Task)
		if ($pendingTask->site_id == 0)
		{
			$logger->info(
				sprintf(
					'System Task #%d — “%s”',
					$pendingTask->id,
					TaskUtils::getTaskDescription($pendingTask->type)
				),
				$pendingTask->getData()
			);
		}
		// Log the task (Site Task)
		else
		{
			$logger->info(
				sprintf(
					'Site Task #%d — “%s” for site #%d (%s)',
					$pendingTask->id,
					TaskUtils::getTaskDescription($pendingTask->type),
					$pendingTask->site_id,
					TaskUtils::getSiteName($pendingTask->site_id)
				),
				$pendingTask->getData()
			);
		}

		// Mark the current task as running
		try
		{
			$willResume = $pendingTask->last_exit_code == Status::WILL_RESUME->value;

			$pendingTask->save([
				'last_exit_code' => Status::RUNNING,
				'last_execution' => (new Date('now', 'UTC'))->toSql(),
			]);
		}
		catch (Exception)
		{
			$logger->error('Failed to update task execution information', $pendingTask->getData());

			// Failure to save the task means that the task execution has ultimately failed.
			try
			{
				$pendingTask->save([
					'last_exit_code' => Status::NO_LOCK,
					'last_execution' => (new Date('now', 'UTC'))->toSql(),
				]);
			}
			catch (Exception)
			{
				// If that fails to, man, I don't know! Your database died or something?
			}

			$db->unlockTables();

			@ob_end_clean();

			return true;
		}

		try
		{
			$logger->debug('Unlocking tables');

			$db->unlockTables();
		}
		catch (Exception)
		{
			// This should not fail; but if it does, we can survive it.
		}

		// Catch the user abort signal, in case we are executed over the web (not ideal...)
		if (function_exists('ignore_user_abort'))
		{
			ignore_user_abort(true);
		}

		// Install a PHP timeout trap
		register_shutdown_function([$this, 'timeoutTrap'], $pendingTask);

		try
		{
			/** @var CallbackInterface $callback */
			$callback = $this->container->taskRegistry->get($pendingTask->type);

			if (function_exists('user_decorate_task'))
			{
				$callback = user_decorate_task($pendingTask->type, $callback);
			}

			if (!$callback instanceof CallbackInterface)
			{
				throw new InvalidTaskType;
			}

			if ($callback instanceof LoggerAwareInterface && !($callback instanceof AbstractCallback))
			{
				$callback->setLogger($logger);
			}

			if ($callback instanceof SymfonyStyleAwareInterface)
			{
				$callback->setSymfonyStyle($ioStyle);
			}

			$taskObject = (object) $pendingTask->toArray();
			$storage    = $pendingTask->storage;

			if (!is_object($storage))
			{
				$pendingTask->storage = new Registry($storage);
				$storage              = $pendingTask->storage;
			}

			$storage->set('task.resumed', $willResume);

			$logger->debug(
				$willResume
					? 'Resuming task'
					: 'Executing task'
			);

			$return = $callback($taskObject, $storage);

			$storage->set('task.resumed', null);

			// Only advance the execution counter if we're not resuming a task.
			if (!$willResume)
			{
				$pendingTask->times_executed++;
			}

			if (empty($return) && $return !== 0)
			{
				$logger->notice('Task finished without an exit status');

				$pendingTask->last_exit_code = Status::NO_EXIT->value;
			}
			elseif (!is_numeric($return))
			{
				$logger->notice('Task finished with an invalid exit value type');

				$pendingTask->last_exit_code = Status::INVALID_EXIT->value;
			}
			else
			{
				$pendingTask->last_exit_code = Status::tryFrom($return)?->value ?? Status::INVALID_EXIT->value;
			}

			if ($pendingTask->last_exit_code === Status::INVALID_EXIT->value)
			{
				$logger->notice('Task finished with an invalid exit value');
			}
			else
			{
				$logger->info(sprintf('Task finished with status “%s”', Status::tryFrom($pendingTask->last_exit_code)->forHumans()));
			}

			if ($pendingTask->last_exit_code !== Status::WILL_RESUME->value)
			{
				$pendingTask->storage        = '{}';
				$cronExpression              = new CronExpression($pendingTask->cron_expression);
				$lastExecution               = new Date($pendingTask->last_execution ?: 'now', 'UTC');
				$nextRun                     = $cronExpression
					->getNextRunDate($lastExecution)->format(DATE_RFC822);
				$pendingTask->next_execution = (new Date($nextRun, 'UTC'))->toSql();
			}
			else
			{
				$pendingTask->storage = $storage->toString();
			}
		}
		catch (InvalidTaskType)
		{
			$logger->error(sprintf('Unknown Task type ‘%s’', $pendingTask->type));

			$pendingTask->last_exit_code = Status::NO_ROUTINE->value;
			$pendingTask->storage->loadString('{}');
			$pendingTask->times_failed++;
		}
		catch (Throwable $e)
		{
			$logger->error(sprintf(
				'Task failed with exception type %s [%s:%d]: %s',
				get_class($e),
				$e->getFile(),
				$e->getLine(),
				$e->getMessage()
			));

			$pendingTask->last_exit_code = Status::EXCEPTION->value;
			$pendingTask->storage        = json_encode([
				'error' => $e->getMessage(),
				'trace' => $e->getFile() . '::' . $e->getLine() . "\n" . $e->getTraceAsString(),
			]);
			$pendingTask->times_failed++;
		}
		finally
		{
			$params        = is_object($pendingTask->params) ? $pendingTask->params : new Registry($pendingTask->params);
			$isInvalidTask = $pendingTask->last_exit_code === Status::NO_ROUTINE->value;
			$isError       = $pendingTask->last_exit_code === Status::EXCEPTION->value;
			$isResumable   = $pendingTask->last_exit_code === Status::WILL_RESUME->value;
			$runOnceAction = $params->get('run_once', null);

			if (($runOnceAction === 'disable') && !$isError && !$isInvalidTask && !$isResumable)
			{
				$logger->debug('Run Once task: action set to disable; disabling task');

				$pendingTask->enabled = 0;
			}

			if (!empty($runOnceAction) && ($isError || $isInvalidTask))
			{
				if ($isError)
				{
					$logger->debug('Run Once task: finished with error; disabling task');
				}
				elseif ($isInvalidTask)
				{
					$logger->debug('Run Once task: invalid task type; disabling task');
				}

				$pendingTask->enabled = 0;
			}

			$logger->debug('Updating the task\'s last execution information');

			try
			{
				$db->lockTable($this->tableName);
				$pendingTask->save([
					'last_run_end' => (new Date('now', 'UTC'))->toSql(),
				]);
				$db->unlockTables();
			}
			catch (Exception)
			{
				$logger->error('Failed to update the task\'s last execution information');

				$pendingTask->save([
					'last_run_end'   => (new Date('now', 'UTC'))->toSql(),
					'last_exit_code' => Status::NO_RELEASE,
				]);
			}

			if (($runOnceAction === 'delete') && !$isError && !$isInvalidTask && !$isResumable)
			{
				$logger->debug('Run Once task: action set to delete; deleting task');

				try
				{
					$pendingTask->delete();
				}
				catch (Exception $e)
				{
					// Don't worry about it.
				}
			}

			@ob_end_clean();

			return true;
		}
	}

	public function cleanUpStuckTasks(): void
	{
		$db         = $this->getDbo();
		$threshold  = max(3, (int) $this->container->appConfig->get('cron_stuck_threshold', 3));
		$cutoffTime = (new Date('now', 'UTC'))->sub(new DateInterval('PT' . $threshold . 'M'));

		$query = $db->getQuery(true)
			->update($db->qn($this->tableName))
			->set([
				$db->qn('last_exit_code') . ' = ' . Status::TIMEOUT->value,
				$db->qn('last_run_end') . ' = NOW()',
				$db->qn('storage') . ' = NULL',
			])
			->where([
				$db->qn('last_exit_code') . ' = ' . Status::RUNNING->value,
				$db->qn('last_execution') . ' <= ' . $db->quote($cutoffTime->toSql()),
			]);

		$db->setQuery($query)->execute();
	}

	public function timeoutTrap(self $pendingTask): void
	{
		// The request has timed out. Whomp, whomp.
		if (in_array(connection_status(), [2, 3]))
		{
			$pendingTask->save([
				'last_exit_code' => Status::TIMEOUT,
				'storage'        => null,
			]);

			exit(127);
		}
	}

	private function getNextTask(): ?self
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn($this->tableName))
			->where([
				$db->quoteName('enabled') . ' = 1',
				$db->quoteName('last_exit_code') . ' = ' . Status::WILL_RESUME->value,
			])
			->order($db->qn('last_run_end') . ' DESC')
			->union(
				$db->getQuery(true)
					->select('*')
					->from($db->qn($this->tableName, 's'))
					->where([
						$db->quoteName('enabled') . ' = 1',
						$db->qn('last_exit_code') . ' != ' . Status::WILL_RESUME->value,
						$db->qn('last_exit_code') . ' != ' . Status::RUNNING->value,
						$db->qn('next_execution') . ' <= ' . $db->quote((new Date('now', 'UTC'))->toSql()),
					])
					->order($db->qn('priority') . ' ASC, ' . $db->qn('id') . ' ASC')
			);

		$tasks = $db->setQuery($query)->loadObjectList();

		if (empty($tasks))
		{
			return null;
		}

		$now = new DateTimeImmutable();

		try
		{
			$tz = $this->container->appConfig->get('timezone', 'UTC');

			// Do not remove. This tests the validity of the configured timezone.
			new DateTimeZone($tz);
		}
		catch (Exception)
		{
			$tz = 'UTC';
		}

		$nullDate = $this->getDbo()->getNullDate();

		foreach ($tasks as $task)
		{
			if ($task->last_exit_code == Status::WILL_RESUME->value)
			{
				return $this->getClone()->bind($task);
			}

			$previousRunStamp = $task->last_run_start ?? '2000-01-01 00:00:00';
			$previousRunStamp = $previousRunStamp === $nullDate ? '2000-01-01 00:00:00' : $previousRunStamp;
			try
			{
				$previousRun  = new DateTime($previousRunStamp);
				$relativeTime = $previousRun;
			}
			catch (Exception)
			{
				$previousRun  = new DateTime('2000-01-01 00:00:00');
				$relativeTime = new DateTime('now');
			}

			$cronParser = new CronExpression($task->cron_expression);
			$nextRun    = $cronParser->getNextRunDate($relativeTime, 0, false, $tz);

			// A task is pending if its next run is after its last run but before the current date and time
			if ($nextRun > $previousRun && $nextRun <= $now)
			{
				return $this->getClone()->bind($task);
			}
		}

		return null;
	}
}