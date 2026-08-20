<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;
use RuntimeException;

/**
 * Tests the fallback paths in {@see Task::runNextTask()} for gh-1060 cause 2.
 *
 * When the "mark as running" or the "bookkeeping last_run_end" save fails, the fallback save
 * must preserve Status::WILL_RESUME instead of unconditionally downgrading to Status::NO_LOCK
 * or Status::NO_RELEASE. Otherwise the bad CRON recalc from cause 1 strands the in-progress
 * batch ~1 week out.
 *
 * Test-coverage note: cross-process behaviour (cause 3) is not exercised here. The fallback
 * regression risk is local and structural — it is fully covered by the two tests below. The
 * cross-process lock-holding behaviour was verified manually by the original reporter with
 * two `php cli/panopticon.php task:run --loop` processes against a test DB.
 *
 * @since 2.3.2
 */
class TaskFallbackPathsTest extends AbstractIntegrationTestCase
{
	private const TEST_TASK_TYPE = 'test_fallback_task';

	private bool $throwOnMarkAsRunning = false;

	private bool $throwOnLastRunEnd = false;

	private int $callbackReturnStatus = Status::OK->value;

	protected function setUp(): void
	{
		parent::setUp();

		// Make sure there is no other task that might be picked up by getNextTask().
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__tasks'));
		$db->setQuery($query)->execute();

		// Register a no-op callback for our test task type.
		$this->container->taskRegistry->add(self::TEST_TASK_TYPE, new class($this) implements CallbackInterface
		{
			public function __construct(private readonly TaskFallbackPathsTest $owner) {}

			public function __invoke(object $task, Registry $storage): int
			{
				return $this->owner->getCallbackReturnStatus();
			}

			public function getTaskType(): string
			{
				return TaskFallbackPathsTest::TEST_TASK_TYPE;
			}

			public function getDescription(): string
			{
				return 'Test fallback task callback';
			}
		});
	}

	/**
	 * Insert a task row directly so the test controls every column value.
	 *
	 * @param   array<string, mixed>  $overrides
	 */
	private function makeTask(array $overrides = []): Task
	{
		$task = new Task($this->container);

		$defaults = [
			'site_id'         => null,
			'type'            => self::TEST_TASK_TYPE,
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
		];

		$data = array_merge($defaults, $overrides);

		foreach (['params', 'storage'] as $jsonField)
		{
			if (is_array($data[$jsonField] ?? null))
			{
				$data[$jsonField] = json_encode($data[$jsonField], JSON_THROW_ON_ERROR);
			}
		}

		// Disable the CRON recalculation so the explicit next_execution survives the initial save.
		// Without this, the recalc in Task::check() rewrites next_execution to the next CRON match,
		// which is the next minute for `* * * * *` — i.e. always in the future — and getNextTask()
		// would not return this task on the next run.
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

	private function makeOverrideableTask(): Task
	{
		// Use a runtime subclass that honours the throw flags so we can drive the fallback
		// paths without rebuilding the whole task infrastructure.
		return new class($this->container, $this) extends Task
		{
			public bool $throwMark = false;

			public bool $throwEnd = false;

			public function __construct(\Awf\Container\Container $container, private readonly TaskFallbackPathsTest $owner)
			{
				parent::__construct($container);
				$this->throwMark = $owner->shouldThrowOnMarkAsRunning();
				$this->throwEnd  = $owner->shouldThrowOnLastRunEnd();
			}

			protected function saveMarkAsRunning(Task $pendingTask, array $updates): void
			{
				if ($this->throwMark)
				{
					throw new RuntimeException('Simulated mark-as-running save failure');
				}

				parent::saveMarkAsRunning($pendingTask, $updates);
			}

			protected function saveLastRunEnd(Task $pendingTask): void
			{
				if ($this->throwEnd)
				{
					throw new RuntimeException('Simulated bookkeeping save failure');
				}

				parent::saveLastRunEnd($pendingTask);
			}
		};
	}

	public function shouldThrowOnMarkAsRunning(): bool
	{
		return $this->throwOnMarkAsRunning;
	}

	public function shouldThrowOnLastRunEnd(): bool
	{
		return $this->throwOnLastRunEnd;
	}

	public function getCallbackReturnStatus(): int
	{
		return $this->callbackReturnStatus;
	}

	public function testSaveFailureOnMarkAsRunningPreservesWillResume(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::WILL_RESUME->value,
				'priority'       => 0,
			]
		);

		$this->throwOnMarkAsRunning = true;

		$model = $this->makeOverrideableTask();
		$model->runNextTask();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::WILL_RESUME->value,
			(int) $reloaded->last_exit_code,
			'Mark-as-running fallback must preserve WILL_RESUME when the original status was WILL_RESUME.'
		);
	}

	public function testSaveFailureOnMarkAsRunningDowngradesNonResume(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'priority'       => 0,
			]
		);

		$this->throwOnMarkAsRunning = true;

		$model = $this->makeOverrideableTask();
		$model->runNextTask();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::NO_LOCK->value,
			(int) $reloaded->last_exit_code,
			'Mark-as-running fallback must downgrade non-WILL_RESUME tasks to NO_LOCK.'
		);
	}

	public function testSaveFailureOnBookkeepingPreservesWillResume(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::WILL_RESUME->value,
				'priority'       => 0,
			]
		);

		// The bookkeeping save runs AFTER the callback. To exercise the WILL_RESUME preservation
		// branch we must have the callback itself return WILL_RESUME; otherwise $priorExitCode
		// is whatever the callback returned (OK in this test's default) and the fallback path
		// uses NO_RELEASE — which is the right behaviour but not what this assertion is checking.
		$this->callbackReturnStatus = Status::WILL_RESUME->value;
		$this->throwOnLastRunEnd    = true;

		$model = $this->makeOverrideableTask();
		$model->runNextTask();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::WILL_RESUME->value,
			(int) $reloaded->last_exit_code,
			'Bookkeeping fallback must preserve WILL_RESUME when the callback returned WILL_RESUME.'
		);
	}

	public function testSaveFailureOnBookkeepingDowngradesNonResume(): void
	{
		$task = $this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'priority'       => 0,
			]
		);

		$this->callbackReturnStatus = Status::OK->value;
		$this->throwOnLastRunEnd    = true;

		$model = $this->makeOverrideableTask();
		$model->runNextTask();

		$reloaded = $this->reload($task);

		$this->assertSame(
			Status::NO_RELEASE->value,
			(int) $reloaded->last_exit_code,
			'Bookkeeping fallback must downgrade non-WILL_RESUME callback outcomes to NO_RELEASE.'
		);
	}
}