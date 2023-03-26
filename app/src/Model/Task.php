<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model;

use Akeeba\Panopticon\Library\Task\Status;
use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Cron\CronExpression;

defined('AKEEBA') || die;

/**
 * Handles the task queue
 *
 * @property int            $id              Task ID
 * @property int            $site_id         The site the task belongs to, 0 = system task
 * @property string         $type            Task type
 * @property Registry       $params          Task parameters
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
			$previousRun          = $this->cron_expression->getPreviousRunDate()->format(DATE_W3C);
			$this->last_execution = (new Date($previousRun))->toSql();
			$this->last_run_end   = null;
			$this->last_exit_code = Status::INITIAL_SCHEDULE;
		}

		return $this;
	}

	// TODO private function getTaskRoutine(self $task): ?callable

	// TODO public function runNextTask(): void

	// TODO public function cleanUpStuckTasks(): void

	// TODO private function getNextTask(): ?self


	protected function set_site_id_attribute(int $site_id): int
	{
		if ($site_id === 0)
		{
			return 0;
		}

		// TODO Check if it's valid
		return $site_id;
	}

	protected function set_type_attribute(string $type): string
	{
		// TODO Check if it's valid
		return $type;
	}

	protected function set_params_attribute(string $params): Registry
	{
		return new Registry($params);
	}

	protected function get_params_attribute(Registry $params): string
	{
		return $params->toString();
	}

	protected function set_cron_expression_attribute(string $cronExpression): CronExpression
	{
		if (empty($cronExpression) || !CronExpression::isValidExpression($cronExpression))
		{
			// TODO create exception
			throw new InvalidCronExpression();
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
}