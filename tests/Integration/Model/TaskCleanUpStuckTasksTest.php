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
use Awf\Registry\Registry;

/**
 * Tests {@see Task::cleanUpStuckTasks()} for gh-1060 cause 8.
 *
 * Two related fixes:
 *
 * 1. The staleness check uses COALESCE(last_run_end, last_execution) so a healthy WILL_RESUME
 *    pickup (which deliberately does not update last_execution) is not falsely flagged as
 *    stuck and wiped.
 *
 * 2. The cleanup now retries up to MAX_STUCK_RETRIES times before giving up. For multi-item
 *    batches (e.g. extensioninstall) the current site is marked failed and the index
 *    advances; for everything else the original TIMEOUT / null-storage behaviour applies.
 *
 * @since 2.3.2
 */
class TaskCleanUpStuckTasksTest extends AbstractIntegrationTestCase
{
	private const MAX_RETRIES = 3;

	protected function setUp(): void
	{
		parent::setUp();

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__tasks'));
		$db->setQuery($query)->execute();

		// Lower the stuck threshold so the test fixtures (ancient timestamps) reliably look stuck.
		$this->container->appConfig->set('cron_stuck_threshold', 3);
	}

	/**
	 * Insert a task row directly with control over every column.
	 */
	private function makeTask(array $overrides = []): Task
	{
		$task = new Task($this->container);

		$defaults = [
			'site_id'         => null,
			'type'            => 'extensioninstall',
			'params'          => '{}',
			'storage'         => '{}',
			'cron_expression' => '* * * * *',
			'enabled'         => 1,
			'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
			'last_execution'  => null,
			'last_run_end'    => null,
			'next_execution'  => $this->container->dateFactory('now - 1 minute', 'UTC')->toSql(),
			'times_executed'  => 0,
			'times_failed'    => 0,
			'locked'          => null,
			'priority'        => 1,
			'stuck_retries'   => 0,
		];

		$data = array_merge($defaults, $overrides);

		foreach (['params', 'storage'] as $jsonField)
		{
			if (is_array($data[$jsonField] ?? null))
			{
				$data[$jsonField] = json_encode($data[$jsonField], JSON_THROW_ON_ERROR);
			}
		}

		$task->setState('disable_next_execution_recalculation', true);
		$task->bind($data);
		$task->save();

		return $task;
	}

	private function reload(Task $task): Task
	{
		$reloaded = new Task($this->container);
		$reloaded->findOrFail($task->getId());

		return $reloaded;
	}

	/**
	 * Regression guard for the COALESCE(last_run_end, last_execution) change: a task with
	 * ancient last_execution but recent last_run_end must NOT be touched by cleanUpStuckTasks().
	 */
	public function testHealthyLongRunningTaskIsNotMarkedStuck(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::RUNNING->value,
				// Very old last_execution (would have flagged as stuck under the old query).
				'last_execution' => $this->container->dateFactory('now - 1 day', 'UTC')->toSql(),
				// But a recent last_run_end (the WILL_RESUME pickup marker).
				'last_run_end'   => $this->container->dateFactory('now - 1 minute', 'UTC')->toSql(),
			]
		);

		$model = new Task($this->container);
		$model->cleanUpStuckTasks();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::RUNNING->value,
			(int) $reloaded->last_exit_code,
			'A healthy long-running task with a recent last_run_end must not be cleaned up.'
		);
	}

	public function testGenuinelyStuckTaskIsRetriedAsWillResume(): void
	{
		$storage = new Registry(
			[
				'currentIndex' => 2,
				'results'      => [1 => ['status' => 'ok'], 2 => ['status' => 'ok']],
			]
		);

		$task = $this->makeTask(
			[
				'last_exit_code' => Status::RUNNING->value,
				'last_execution' => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'last_run_end'   => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'storage'        => $storage->toString(),
				'stuck_retries'  => 0,
			]
		);

		$model = new Task($this->container);
		$model->cleanUpStuckTasks();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::WILL_RESUME->value,
			(int) $reloaded->last_exit_code,
			'A stuck task with retries remaining must be restored to WILL_RESUME.'
		);
		$this->assertSame(
			1,
			(int) $reloaded->stuck_retries,
			'stuck_retries must be incremented on each retry.'
		);

		$reloadedStorage = $reloaded->getStorage();
		$this->assertSame(
			2,
			(int) $reloadedStorage->get('currentIndex', -1),
			'Storage must be preserved across the retry.'
		);
	}

	public function testStuckTaskAtRetryCapIsMarkedTimeout(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::RUNNING->value,
				'last_execution' => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'last_run_end'   => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'storage'        => '{"currentIndex":0,"results":{}}',
				// No sites in params → falls into the "not a multi-item batch" branch.
				'params'         => '{}',
				'stuck_retries'  => self::MAX_RETRIES,
			]
		);

		$model = new Task($this->container);
		$model->cleanUpStuckTasks();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::TIMEOUT->value,
			(int) $reloaded->last_exit_code,
			'A task past the retry cap and without a multi-item batch must be marked TIMEOUT.'
		);

		$reloadedStorage = $reloaded->getStorage();
		$this->assertSame(
			'{}',
			$reloadedStorage->toString(),
			'Storage must be wiped on TIMEOUT for non-multi-item tasks.'
		);
	}

	public function testMultiItemBatchAtRetryCapAdvancesCurrentIndex(): void
	{
		$params = ['sites' => [1, 2, 3]];
		$storage = new Registry(
			[
				'currentIndex' => 1,
				'results'      => [1 => ['status' => 'ok']],
			]
		);

		$task = $this->makeTask(
			[
				'last_exit_code' => Status::RUNNING->value,
				'last_execution' => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'last_run_end'   => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
				'params'         => $params,
				'storage'        => $storage->toString(),
				'stuck_retries'  => self::MAX_RETRIES,
			]
		);

		$model = new Task($this->container);
		$model->cleanUpStuckTasks();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::WILL_RESUME->value,
			(int) $reloaded->last_exit_code,
			'A multi-item batch past the retry cap must keep going (WILL_RESUME) when more sites remain.'
		);

		$reloadedStorage = $reloaded->getStorage();
		$this->assertSame(
			2,
			(int) $reloadedStorage->get('currentIndex', -1),
			'currentIndex must advance past the failed site.'
		);

		$results = $reloadedStorage->get('results', []);
		$this->assertIsObject($results, 'results must be an object after a round-trip through the storage JSON.');
		$this->assertTrue(property_exists($results, '2'), 'A failed entry must be written for the current site.');
		$this->assertSame(
			'failed',
			$results->{'2'}->status ?? null,
			'The current site must be marked as failed.'
		);
	}
}