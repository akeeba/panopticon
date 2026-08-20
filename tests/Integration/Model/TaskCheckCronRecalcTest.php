<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;

/**
 * Tests the CRON recalculation behaviour in {@see Task::check()} for gh-1060.
 *
 * Regression guard for the WILL_RESUME branch in check(): a task returning
 * Status::WILL_RESUME must have its next_execution pushed to "now + 2s" so it
 * resumes on the next cron tick instead of being stranded ~1 week out by a
 * bad CRON recalculation against the controller-pinned one-shot expression.
 *
 * @since 2.3.2
 */
class TaskCheckCronRecalcTest extends AbstractIntegrationTestCase
{
	/**
	 * Insert a task row directly, bypassing check() so the test controls every column value.
	 *
	 * @param   array<string, mixed>  $overrides
	 *
	 * @return  Task
	 */
	private function makeTask(array $overrides = []): Task
	{
		$task = new Task($this->container);

		$defaults = [
			'site_id'         => null,
			'type'            => 'extensioninstall',
			'params'          => '{}',
			'storage'         => '{}',
			// A pinned "run immediately" one-shot CRON expression like the
			// Extensioninstall controller produces. Will resolve ~7 days out.
			'cron_expression' => '30 14 5 8 2',
			'enabled'         => 1,
			'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
			'last_execution'  => null,
			'last_run_end'    => null,
			'next_execution'  => '2000-01-01 00:00:00',
			'times_executed'  => 0,
			'times_failed'    => 0,
			'locked'          => null,
			'priority'        => 1,
		];

		$data = array_merge($defaults, $overrides);

		// JSON columns must be JSON strings when bound.
		foreach (['params', 'storage'] as $jsonField)
		{
			if (is_array($data[$jsonField] ?? null))
			{
				$data[$jsonField] = json_encode($data[$jsonField], JSON_THROW_ON_ERROR);
			}
		}

		$task->bind($data);

		return $task;
	}

	/**
	 * Reload a task row from the database.
	 */
	private function reload(Task $task): Task
	{
		$reloaded = new Task($this->container);
		$reloaded->findOrFail($task->getId());

		return $reloaded;
	}

	public function testCheckOnWillResumeSetsNextExecutionToImmediate(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::WILL_RESUME->value,
				// Sanity: the bad CRON expression will resolve ~7 days out, not 2 seconds.
				'cron_expression' => '30 14 5 8 2',
			]
		);

		$task->setState('disable_next_execution_recalculation', false);
		$task->save();

		$reloaded = $this->reload($task);

		$now          = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$nextExec     = new \DateTimeImmutable((string) $reloaded->next_execution, new \DateTimeZone('UTC'));
		$secondsAhead = $nextExec->getTimestamp() - $now->getTimestamp();

		$this->assertGreaterThanOrEqual(
			0,
			$secondsAhead,
			'next_execution must not be in the past after a WILL_RESUME save'
		);
		$this->assertLessThanOrEqual(
			30,
			$secondsAhead,
			sprintf(
				'next_execution must be ~2 seconds out after a WILL_RESUME save; got %d seconds',
				$secondsAhead
			)
		);
	}

	public function testCheckOnRegularCronStillRespectsExpression(): void
	{
		// A CRON expression that resolves to a known future time. * * * * 3 means
		// "every minute during March" — in any month other than March we resolve to
		// the next March 1st at 00:00 (or, if March is the current month, the next
		// minute at HH:MM:00 during March).
		$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

		if ((int) $now->format('n') === 3)
		{
			// Inside March: every minute. Set a future-but-not-too-far expression.
			$cron = (int) $now->format('i') . ' ' . (int) $now->format('H') . ' ' . (int) $now->format('d') . ' 3 *';
		}
		else
		{
			// Outside March: the first second of the next March 1st. Avoid Feb 29 / 30
			// edge cases by using the last day of February 28 in a non-leap year. For
			// 2026 that's already past, so pick a date that always exists.
			$cron = '0 0 28 2 *';
		}

		$task = $this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'cron_expression' => $cron,
			]
		);

		$task->setState('disable_next_execution_recalculation', false);
		$task->save();

		$reloaded = $this->reload($task);

		// Resolve the same CRON expression ourselves for the comparison.
		$cronExpr  = new \Cron\CronExpression($cron);
		$nextRun   = $cronExpr->getNextRunDate('now')->format('Y-m-d H:i:s');
		$nextExec  = new \DateTimeImmutable((string) $reloaded->next_execution, new \DateTimeZone('UTC'));
		$expected  = new \DateTimeImmutable($nextRun, new \DateTimeZone('UTC'));

		// Allow a 1-minute slop for "the cron resolution crosses a minute boundary mid-test".
		$delta = abs($nextExec->getTimestamp() - $expected->getTimestamp());

		$this->assertLessThanOrEqual(
			60,
			$delta,
			sprintf(
				'next_execution must follow the CRON expression; got %s, expected ~%s',
				$nextExec->format('c'),
				$expected->format('c')
			)
		);
	}

	public function testDisableNextExecutionRecalcStateStillWins(): void
	{
		// A pre-known next_execution we expect to be preserved verbatim.
		$knownNext = '2099-12-31 23:59:59';

		$task = $this->makeTask(
			[
				'last_exit_code' => Status::WILL_RESUME->value,
				'next_execution' => $knownNext,
			]
		);

		// Even with WILL_RESUME, the state guard must prevent the recalculation.
		$task->setState('disable_next_execution_recalculation', true);
		$task->save();

		$reloaded = $this->reload($task);

		$this->assertSame(
			$knownNext,
			(string) $reloaded->next_execution,
			'disable_next_execution_recalculation state must prevent any next_execution change'
		);
	}
}