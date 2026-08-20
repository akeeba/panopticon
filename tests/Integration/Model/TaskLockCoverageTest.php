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
use ReflectionProperty;

/**
 * Tests that the named DB lock in {@see Task::runNextTask()} is held continuously across the
 * cleanUpStuckTasks → getNextTask → mark-as-running critical section.
 *
 * gh-1060 cause 3: the lock was previously released between cleanUpStuckTasks() and
 * getNextTask(), letting two concurrent processes grab the same WILL_RESUME row and trip
 * AWF's optimistic-locking "Record has changed since last read" or a MySQL deadlock.
 *
 * Cross-process concurrency cannot be reproduced in a unit test. The reporter verified the
 * fix manually with two `php cli/panopticon.php task:run --loop` processes against a test DB.
 * The structural test below confirms the in-process invariant: the lock flag is true at
 * every observable point inside the critical section.
 *
 * @since 2.3.2
 */
class TaskLockCoverageTest extends AbstractIntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__tasks'));
		$db->setQuery($query)->execute();

		$this->container->taskRegistry->add('test_lock', new class implements CallbackInterface
		{
			public function __invoke(object $task, Registry $storage): int
			{
				return Status::OK->value;
			}

			public function getTaskType(): string
			{
				return 'test_lock';
			}

			public function getDescription(): string
			{
				return 'Test lock-coverage callback';
			}
		});
	}

	/**
	 * Read the value of the private $lockHeld property on a Task instance.
	 */
	private function readLockHeldFlag(Task $model): bool
	{
		$ref = new ReflectionProperty(Task::class, 'lockHeld');
		$ref->setAccessible(true);

		return (bool) $ref->getValue($model);
	}

	/**
	 * Insert a task row that getNextTask() will return.
	 */
	private function makeTask(): Task
	{
		$task = new Task($this->container);
		$task->setState('disable_next_execution_recalculation', true);
		$task->bind(
			[
				'site_id'         => null,
				'type'            => 'test_lock',
				'params'          => '{}',
				'storage'         => '{}',
				'cron_expression' => '* * * * *',
				'enabled'         => 1,
				'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
				'next_execution'  => $this->container->dateFactory('now - 1 minute', 'UTC')->toSql(),
				'times_executed'  => 0,
				'times_failed'    => 0,
				'priority'        => 1,
			]
		);
		$task->save();

		return $task;
	}

	/**
	 * The critical section must observe the lock as held at every point we can instrument.
	 * Each observable method captures the lockHeld flag at the moment it runs and stores it
	 * on the test instance for post-hoc assertion.
	 */
	public function testLockIsHeldContinuouslyAcrossTheCriticalSection(): void
	{
		$this->makeTask();

		$model = new class($this->container, $this) extends Task
		{
			public function __construct(
				\Awf\Container\Container $container,
				private readonly TaskLockCoverageTest $owner
			)
			{
				parent::__construct($container);
			}

			public function cleanUpStuckTasks(): void
			{
				$this->owner->recordLockDuringCleanUp($this->isLockHeld());
				parent::cleanUpStuckTasks();
			}

			protected function saveMarkAsRunning(Task $pendingTask, array $updates): void
			{
				$this->owner->recordLockDuringMarkAsRunning($this->isLockHeld());
				parent::saveMarkAsRunning($pendingTask, $updates);
			}

			public function isLockHeld(): bool
			{
				$ref = new ReflectionProperty(Task::class, 'lockHeld');
				$ref->setAccessible(true);

				return (bool) $ref->getValue($this);
			}
		};

		$model->runNextTask();

		$this->assertNotNull(
			$this->lockDuringCleanUpStuckTasks,
			'cleanUpStuckTasks override should have been called.'
		);
		$this->assertNotNull(
			$this->lockDuringMarkAsRunning,
			'saveMarkAsRunning override should have been called.'
		);

		$this->assertTrue(
			$this->lockDuringCleanUpStuckTasks,
			'Lock must be held when cleanUpStuckTasks() runs.'
		);
		$this->assertTrue(
			$this->lockDuringMarkAsRunning,
			'Lock must still be held when the mark-as-running save runs (cause 3 regression guard).'
		);

		// And after runNextTask() returns the lock must have been released.
		$this->assertFalse(
			$this->readLockHeldFlag($model),
			'Lock must be released after a successful runNextTask().'
		);
	}

	/**
	 * Recording shims for the override methods on the anonymous subclass.
	 *
	 * @internal
	 */
	public function recordLockDuringCleanUp(bool $value): void
	{
		$this->lockDuringCleanUpStuckTasks = $value;
	}

	public function recordLockDuringMarkAsRunning(bool $value): void
	{
		$this->lockDuringMarkAsRunning = $value;
	}

	public ?bool $lockDuringCleanUpStuckTasks = null;

	public ?bool $lockDuringMarkAsRunning = null;
}