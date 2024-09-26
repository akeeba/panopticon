<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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
 * @property int            $last_exit_code  Last execution's exit code
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
	private const DB_LOCK_NAME = 'PanopticonNextTask';

	private const DB_LOCK_TIMEOUT = 5;

	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__tasks';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function buildQuery($overrideLimits = false)
	{
		/**
		 * Workaround for filtering on site_id = 0.
		 *
		 * System tasks have a site_id = NULL. However, we cannot pass NULL as a query parameter. The closest thing is
		 * either an empty string, or the value 0. An empty string in AWF means "no filter", therefor we use the value
		 * 0. However, in this case, AWF will filter for value 0 which does not actually exist in our table. Therefore,
		 * we need to do some sleight of hand.
		 *
		 * When we detect this condition we set the $filterSystemTask flag. When this flag is set, we do two things.
		 *
		 * First, we unset the site_id state variable before constructing the query. This bypasses AWF's filters, i.e.
		 * AWF will not add a WHERE site_id = 0. This of course means that it does no filtering.
		 *
		 * Hence, the second part, after we construct the query. We add WHERE site_id IS NULL manually, and restore the
		 * state variable back to '0', so that the interface will display the sites dropdown correctly. Ta-da!
		 */
		$filterSystemTask = $this->getState('site_id') === '0';

		if ($filterSystemTask)
		{
			$this->setState('site_id', '');
		}

		$query = parent::buildQuery($overrideLimits);

		// Part two of the sleight-of-hand explained above.
		if ($filterSystemTask)
		{
			$query->where(
				$query->quoteName('site_id') . ' IS NULL'
			);

			$this->setState('site_id', '0');
		}

		return $query;
	}

	public function getParams(): Registry
	{
		return $this->params instanceof Registry ? $this->params : new Registry($this->params);
	}

	public function getStorage(): Registry
	{
		return $this->storage instanceof Registry ? $this->storage : new Registry($this->storage);
	}

	/**
	 * Get the duration of the last execution
	 *
	 * @return  string|null
	 * @since   1.0.0
	 */
	public function getDuration(): ?string
	{
		// Some exit codes imply no duration
		if (
			in_array(
				$this->last_exit_code,
				[
					Status::INITIAL_SCHEDULE->value,
					Status::NO_ROUTINE->value,
					Status::NO_LOCK->value,
					Status::NO_RELEASE->value,
					Status::NO_TASK->value,
					Status::NO_RUN->value,
				]
			)
		)
		{
			return null;
		}

		// We need to have valid execution start/stop timestamps.
		if (
			empty($this->last_execution) || empty($this->last_run_end) ||
			$this->last_execution == '0000-00-00 00:00:00' || $this->last_run_end == '0000-00-00 00:00:00' ||
			$this->last_execution == '2000-01-01 00:00:00' || $this->last_run_end == '2000-01-01 00:00:00'
		)
		{
			return null;
		}

		$utcTimeZone = new DateTimeZone('UTC');
		$startTime   = clone $this->container->dateFactory($this->last_execution, $utcTimeZone);
		$endTime     = clone $this->container->dateFactory($this->last_run_end, $utcTimeZone);

		$duration = abs($endTime->toUnix() - $startTime->toUnix());

		$seconds  = $duration % 60;
		$duration -= $seconds;

		$minutes  = ($duration % 3600) / 60;
		$duration -= $minutes * 60;

		$hours = $duration / 3600;

		return sprintf('%02d', $hours) . ':' .
			sprintf('%02d', $minutes) . ':' .
			sprintf('%02d', $seconds);

	}

	public function check(): self
	{
		parent::check();

		// Check the cron_expression for validity
		if (!CronExpression::isValidExpression($this->cron_expression))
		{
			new CronExpression($this->cron_expression);
		}

		// I always need to set the next run when I am saving a task.
		try
		{
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

			$cron_expression      = $this->cron_expression instanceof CronExpression
				? $this->cron_expression
				: new CronExpression($this->cron_expression);
			// Warning! The last execution time is ALWAYS stored in UTC
			$relativeTime         = ($this->container->dateFactory($this->last_execution ?: 'now', 'UTC'))->format('Y-m-d\TH:i:sP', false, false);
			// The call to getNextRunDate must use our local timezone because the CRON expression is in local time
			$nextRun              = $cron_expression->getNextRunDate($relativeTime, timeZone: $tz)->format(DATE_W3C);

			/**
			 * If the disable_next_execution_recalculation state is set to true we'll NOT override next_execution.
			 *
			 * This is used when scheduling tasks for the current date and time, having them run as soon as possible.
			 */
			if (!$this->getState('disable_next_execution_recalculation', false, 'bool'))
			{
				$this->next_execution = ($this->container->dateFactory($nextRun, 'UTC'))->toSql();
			}
		}
		catch (Exception)
		{
			// Nothing to do
		}

		// If this is a brand new CRON job describe it appropriately.
		if ($this->next_execution === null || $this->next_execution === '2000-01-01 00:00:00')
		{
			$this->last_run_end   = null;
			$this->last_exit_code = Status::INITIAL_SCHEDULE->value;
		}

		return $this;
	}

	public function runNextTask(?LoggerInterface $logger = null, ?SymfonyStyle $ioStyle = null): bool
	{
		$logger ??= new NullLogger();

		@ob_start();

		$logger->info('Locking task tables');

		// Lock the table to avoid concurrency issues
		if (!$this->lockTables($logger))
		{
			return false;
		}

		// Cancel any tasks which appear to be stuck
		try
		{
			$logger->info('Cleaning up stuck tasks');

			$this->cleanUpStuckTasks();

			$this->unlockTables();
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
			$this->unlockTables();

			@ob_end_clean();

			return false;
		}

		// Get the next pending task
		try
		{
			$logger->info('Getting next task');

			/**
			 * MAGIC. DO NOT TOUCH.
			 *
			 * If you have multiple processes trying to get a lock at precisely the same time (down to a few hundreds of
			 * nanoseconds) MySQL will happily acquire the lock for EACH. AND. EVERY. ONE. OF. THEM! This, of course, is
			 * really bloody useless as it beats the entire point of having a lock. Adding a random sleep between 25 and
			 * 100 msec we “unsync” the various threads enough so that MySQL can get its ducks in a row and apply locks
			 * they way its documentation claims it does.
			 *
			 * Almost six hours of debugging comes down to “wait for a random amount of time”. For fuck's sake…
			 */
			usleep(random_int(25000, 100000));

			$pendingTask = $this->getNextTask();

			if (empty($pendingTask))
			{
				$logger->info('There are no pending tasks.');

				$this->unlockTables();

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

			$this->unlockTables();

			@ob_end_clean();

			return false;
		}

		// Log the task (System Task)
		if ($pendingTask->site_id == 0)
		{
			$logger->info(
				sprintf(
					'System Task #%d - “%s”',
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
					'Site Task #%d - “%s” for site #%d (%s)',
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

			$updates = [
				'last_exit_code' => Status::RUNNING->value,
			];

			/**
			 * Update the last execution time only when we are not resuming the task. Otherwise, the time spent on the
			 * task will always be wrong as it will be measured from the beginning of its last step, not the very start
			 * of the task itself.
			 */
			if (!$willResume)
			{
				$updates['last_execution'] = ($this->container->dateFactory('now', 'UTC'))->toSql();
			}

			$pendingTask->save($updates);
		}
		catch (Exception)
		{
			$logger->error('Failed to update task execution information', $pendingTask->getData());

			// Failure to save the task means that the task execution has ultimately failed.
			try
			{
				$pendingTask->save([
					'last_exit_code' => Status::NO_LOCK->value,
					'last_execution' => ($this->container->dateFactory('now', 'UTC'))->toSql(),
				]);
			}
			catch (Exception)
			{
				// If that fails to, man, I don't know! Your database died or something?
			}

			$this->unlockTables();

			@ob_end_clean();

			return true;
		}

		try
		{
			$logger->debug('Unlocking tables');

			$this->unlockTables();
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

			if ($callback instanceof SymfonyStyleAwareInterface && !empty($ioStyle))
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

			$pendingTask->storage = $pendingTask->last_exit_code !== Status::WILL_RESUME->value
				? '{}'
				: $storage->toString();
		}
		catch (InvalidTaskType)
		{
			$logger->error(sprintf('Unknown Task type ‘%s’', $pendingTask->type));

			$pendingTask->last_exit_code = Status::NO_ROUTINE->value;
			$pendingTask->storage = '{}';
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
				$this->lockTables();
				$pendingTask->save([
					'last_run_end' => ($this->container->dateFactory('now', 'UTC'))->toSql(),
				]);
				$this->unlockTables();
			}
			catch (Exception)
			{
				$logger->error('Failed to update the task\'s last execution information');

				$pendingTask->save([
					'last_run_end'   => ($this->container->dateFactory('now', 'UTC'))->toSql(),
					'last_exit_code' => Status::NO_RELEASE->value,
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
		$cutoffTime = $this->container->dateFactory('now', 'UTC')
			->sub(new DateInterval('PT' . $threshold . 'M'));

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
				'last_exit_code' => Status::TIMEOUT->value,
				'storage'        => null,
			]);

			exit(127);
		}
	}

	public function getGroupsForSelect(): array
	{
		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
			])
			->from($db->quoteName('#__groups'));

		return array_map(fn($x) => $x->title, $db->setQuery($query)->loadObjectList('id') ?: []);
	}

	private function getNextTask(): ?self
	{
		$db    = $this->getDbo();

		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn($this->tableName))
			->where($db->quoteName('enabled') . ' = 1')
			->andWhere([
				$db->quoteName('last_exit_code') . ' = ' . Status::WILL_RESUME->value,
				// -OR-
				'(' .
				$db->qn('last_exit_code') . ' != ' . Status::WILL_RESUME->value .
				' AND ' .
				$db->qn('last_exit_code') . ' != ' . Status::RUNNING->value .
				' AND ' .
				$db->qn('next_execution') . ' <= ' . $db->quote(
					$this->container->dateFactory('now', 'UTC')->toSql()
				) .
				')'
			])
			->order(
				$db->qn('priority') . ' ASC, ' .
				$db->qn('last_exit_code') . ' DESC, ' .
				$db->qn('next_execution') . ' ASC'
			);

		$task = $db->setQuery($query, 0, 1)->loadObject();

		if (empty($task))
		{
			return null;
		}

		return $this->getClone()->bind($task);
	}

	private function lockTables(?LoggerInterface $logger = null, bool $unlockFirst = false)
	{
		$logger ??= new NullLogger();
		$db     = $this->getDbo();

		if ($unlockFirst)
		{
			$this->unlockTables();
		}

		$gotLock = $db->setQuery(
			'SELECT GET_LOCK(' . $db->quote(self::DB_LOCK_NAME) . ', ' . self::DB_LOCK_TIMEOUT . ')'
		)->loadResult();

		if ($gotLock == 1)
		{
			$connId = $db->setQuery('SELECT CONNECTION_ID()')->loadResult();

			$logger->debug(sprintf(
				'Got tasks lock [%d]', $connId
			));

			return true;
		}

		$logger->notice('Could not obtain tasks lock');

		return false;
	}

	private function unlockTables()
	{
		$db = $this->getDbo();

		while (true)
		{
			$result = $db->setQuery(
				'SELECT RELEASE_LOCK('.$db->quote(self::DB_LOCK_NAME).')'
			)->loadResult();

			if ($result !== 1)
			{
				break;
			}
		}
	}
}