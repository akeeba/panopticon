<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model;

use Akeeba\Panopticon\Exception\InvalidCronExpression;
use Akeeba\Panopticon\Exception\InvalidTaskType;
use Akeeba\Panopticon\Helper\TaskUtils;
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
				$previousRun          = $this->cron_expression->getPreviousRunDate()->format(DATE_W3C);
				$this->last_execution = (new Date($previousRun))->toSql();
			}
			catch (Exception)
			{
				$this->last_execution = (new Date('2000-01-01 00:00:00'))->toSql();
			}

			$this->last_run_end   = null;
			$this->last_exit_code = Status::INITIAL_SCHEDULE;
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
				. $db->quoteName($this->tableName, 's') . ' WRITE';
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
					'Task retrieval failed [%s:%d]: %s',
					$e->getFile(),
					$e->getLine(),
					$e->getMessage()
				)
			);

			$db->unlockTables();

			@ob_end_clean();

			return false;
		}

		// Mark the current task as running
		try
		{
			$logger->info(
				sprintf(
					'Task #%d — “%s” for site #%d (%s)',
					$pendingTask->id,
					TaskUtils::getTaskDescription($pendingTask->type),
					$pendingTask->site_id,
					TaskUtils::getSiteName($pendingTask->site_id)
				)
			);

			$willResume = $pendingTask->last_exit_code === Status::WILL_RESUME;

			$pendingTask->save([
				'last_exit_code' => Status::RUNNING,
				'last_execution' => (new Date())->toSql(),
			]);
		}
		catch (Exception)
		{
			$logger->error('Failed to update task execution information');

			// Failure to save the task means that the task execution has ultimately failed.
			try
			{
				$pendingTask->save([
					'last_exit_code' => Status::NO_LOCK,
					'last_execution' => (new Date())->toSql(),
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

			if ($callback instanceof LoggerAwareInterface)
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

			if (empty($return) && $return !== 0)
			{
				$logger->notice('Task finished without an exit status');

				$pendingTask->last_exit_code = Status::NO_EXIT;
			}
			elseif (!is_numeric($return))
			{
				$logger->notice('Task finished with an invalid exit value type');

				$pendingTask->last_exit_code = Status::INVALID_EXIT;
			}
			else
			{
				$pendingTask->last_exit_code = Status::tryFrom($return) ?? Status::INVALID_EXIT;
			}

			if ($pendingTask->last_exit_code === Status::INVALID_EXIT)
			{
				$logger->notice('Task finished with an invalid exit value');
			}
			else
			{
				$logger->info(sprintf('Task finished with status “%s”', $pendingTask->last_exit_code->forHumans()));
			}

			if ($pendingTask->last_execution !== Status::WILL_RESUME)
			{
				$pendingTask->storage->loadString('{}');

				$cronExpression = new CronExpression($this->cron_expression);

				$lastExecution        = new DateTime($this->last_execution ?: 'now');
				$nextRun              = $cronExpression
					->getNextRunDate($lastExecution)->format(DATE_W3C);
				$this->next_execution = (new Date($nextRun))->toSql();
			}
		}
		catch (InvalidTaskType)
		{
			$logger->error(sprintf('Unknown Task type ‘%s’', $pendingTask->type));

			$pendingTask->last_exit_code = Status::NO_ROUTINE;
			$pendingTask->storage->loadString('{}');
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

			$pendingTask->last_exit_code = Status::EXCEPTION;
			$pendingTask->storage->loadString(
				json_encode([
					'error' => $e->getMessage(),
					'trace' => $e->getFile() . '::' . $e->getLine() . "\n" . $e->getTraceAsString(),
				])
			);
		}
		finally
		{
			$params        = is_object($pendingTask->params) ? $pendingTask->params : new Registry($pendingTask->params);
			$isInvalidTask = $pendingTask->last_exit_code === Status::NO_ROUTINE;
			$isError       = $pendingTask->last_exit_code === Status::EXCEPTION;
			$runOnceAction = $params->get('run_once', null);

			if (($runOnceAction === 'disable') && !$isError && !$isInvalidTask)
			{
				$logger->debug('Run Once task: action set to disable; disabling task');

				$pendingTask->enabled = 0;
			}

			$logger->debug('Updating the task\'s last execution information');

			try
			{
				$db->lockTable($this->tableName);
				$pendingTask->save([
					'last_run_end' => (new Date())->toSql(),
				]);
				$db->unlockTables();
			}
			catch (Exception)
			{
				$logger->error('Failed to update the task\'s last execution information');

				$pendingTask->save([
					'last_run_end'   => (new Date())->toSql(),
					'last_exit_code' => Status::NO_RELEASE,
				]);
			}

			if (($runOnceAction === 'delete') && !$isError && !$isInvalidTask)
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
		$cutoffTime = (new Date())->sub(new DateInterval('PT' . $threshold . 'M'));

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

	protected function set_site_id_attribute(?int $site_id): int
	{
		if (($site_id ?? 0) === 0)
		{
			return 0;
		}

		// TODO Check if it's valid
		return $site_id;
	}

	protected function set_params_attribute(string $params): Registry
	{
		return new Registry($params);
	}

	protected function get_params_attribute(?Registry $params): ?string
	{
		$ret = $params?->toString();

		if ($ret === '{}' || empty($ret))
		{
			return null;
		}

		return $ret;
	}

	protected function set_storage_attribute(?string $params): Registry
	{
		return new Registry($params ?? '');
	}

	protected function get_storage_attribute(Registry $params): string
	{
		return $params->toString();
	}

	protected function set_cron_expression_attribute(string $cronExpression): CronExpression
	{
		if (empty($cronExpression) || !CronExpression::isValidExpression($cronExpression))
		{
			throw new InvalidCronExpression($cronExpression);
		}

		return new CronExpression($cronExpression);
	}

	protected function get_cron_expression_attribute(CronExpression $cronExpression): string
	{
		return $cronExpression->getExpression();
	}

	protected function set_enabled_attribute(mixed $value): bool
	{
		return boolval($value);
	}

	protected function get_enabled_attribute(bool $enabled): string
	{
		return $enabled ? '1' : '0';
	}

	protected function set_last_exit_code_attribute(string $lastExitCode): Status
	{
		return Status::tryFrom(intval($lastExitCode));
	}

	protected function get_last_execution_attribute(string $value): ?Date
	{
		if (empty($value) || $value === $this->getDbo()->getNullDate())
		{
			return null;
		}

		return new Date($value);
	}

	protected function set_last_execution_attribute(?Date $value): ?string
	{
		return $value?->toSql();
	}

	protected function get_last_run_end_attribute(string $value): ?Date
	{
		if (empty($value) || $value === $this->getDbo()->getNullDate())
		{
			return null;
		}

		return new Date($value);
	}

	protected function set_last_run_end_attribute(?Date $value): ?string
	{
		return $value?->toSql();
	}

	protected function get_next_execution_attribute(string $value): Date
	{
		return new Date($value);
	}

	protected function set_next_execution_attribute(Date $value): string
	{
		return $value->toSql();
	}

	protected function get_times_executed_attribute(mixed $value): int
	{
		return intval($value ?? 0);
	}

	protected function set_times_executed_attribute(int $value): string
	{
		return (string) $value;
	}

	protected function get_times_failed_attribute(mixed $value): int
	{
		return intval($value ?? 0);
	}

	protected function set_times_failed_attribute(int $value): string
	{
		return (string) $value;
	}

	protected function get_locked_attribute(string $value): ?Date
	{
		if (empty($value) || $value === $this->getDbo()->getNullDate())
		{
			return null;
		}

		return new Date($value);
	}

	protected function set_locked_attribute(?Date $value): string
	{
		return $value?->toSql();
	}

	protected function get_priority_attribute(mixed $value): int
	{
		return intval($value ?? 0);
	}

	protected function set_priority_attribute(int $value): string
	{
		return (string) $value;
	}

	protected function get_last_exit_code_attribute(Status $lastExitCode): string
	{
		return (string) $lastExitCode->value;
	}

	private function getNextTask(): ?self
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn($this->tableName))
			->where($db->qn('last_exit_code') . ' = ' . Status::WILL_RESUME->value)
			->order($db->qn('last_run_end') . ' DESC')
			->union(
				$db->getQuery(true)
					->select('*')
					->from($db->qn($this->tableName, 's'))
					->where([
						$db->qn('last_exit_code') . ' != ' . Status::WILL_RESUME->value,
						$db->qn('last_exit_code') . ' != ' . Status::RUNNING->value,
					])
					->order($db->qn('id') . ' DESC')
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
			if ($task->last_exit_code == Status::WILL_RESUME)
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