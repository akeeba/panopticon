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
use ReflectionMethod;

/**
 * Tests the query in {@see Task::getNextTask()} for gh-1060 cause 5.
 *
 * The previous ORDER BY `priority ASC, last_exit_code DESC, next_execution ASC` allowed a
 * `priority = 0` routine task to perpetually queue ahead of a `priority = 1` WILL_RESUME task
 * (the extensioninstall case). The fix introduces a leading CASE expression that puts every
 * WILL_RESUME row ahead of every non-WILL_RESUME row regardless of priority.
 *
 * {@see Task::getNextTask()} is private; we drive it via reflection so we can pin down the
 * ordering precisely without running the full runNextTask() pipeline (which would time out
 * behind the lock + usleep).
 *
 * @since 2.3.2
 */
class TaskGetNextTaskTest extends AbstractIntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__tasks'));
		$db->setQuery($query)->execute();
	}

	/**
	 * Insert a task row directly so the test controls every column value.
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
		$task->setState('disable_next_execution_recalculation', true);
		$task->bind($data);
		$task->save();

		return $task;
	}

	/**
	 * Drive the private {@see Task::getNextTask()} via reflection and return its ID, or null.
	 */
	private function nextTaskId(): ?int
	{
		$model = new Task($this->container);

		$ref = new ReflectionMethod($model, 'getNextTask');
		$ref->setAccessible(true);
		$task = $ref->invoke($model);

		return $task === null ? null : (int) $task->getId();
	}

	public function testWillResumeTaskJumpsAheadOfRoutineTasks(): void
	{
		// Routine task: priority 0 (the system default), OK status, due now.
		$this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'priority'       => 0,
				'cron_expression' => '* * * * *',
			]
		);

		// WILL_RESUME task: priority 1 (the extensioninstall default).
		$willResume = $this->makeTask(
			[
				'last_exit_code' => Status::WILL_RESUME->value,
				'priority'       => 1,
				'cron_expression' => '* * * * *',
			]
		);

		$this->assertSame(
			$willResume->getId(),
			$this->nextTaskId(),
			'A WILL_RESUME task must run before routine tasks, regardless of priority.'
		);
	}

	public function testRoutineTasksStillOrderedByPriorityThenNextExecution(): void
	{
		$earliestPriority1 = $this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'priority'       => 1,
				// Far in the past — would come first by next_execution ASC if priority were ignored.
				'next_execution' => $this->container->dateFactory('now - 1 hour', 'UTC')->toSql(),
			]
		);

		$lowPriorityLater = $this->makeTask(
			[
				'last_exit_code' => Status::OK->value,
				'priority'       => 0,
				// Slightly in the past — would come after the priority-1 task by next_execution.
				'next_execution' => $this->container->dateFactory('now - 30 minutes', 'UTC')->toSql(),
			]
		);

		$this->assertSame(
			$lowPriorityLater->getId(),
			$this->nextTaskId(),
			'Within non-WILL_RESUME tasks the priority-then-next_execution ordering must be preserved.'
		);

		// And once the priority-0 task is "consumed", the priority-1 task should be picked up next.
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__tasks'))
			->where($db->quoteName('id') . ' = ' . (int) $lowPriorityLater->getId());
		$db->setQuery($query)->execute();

		$this->assertSame(
			$earliestPriority1->getId(),
			$this->nextTaskId(),
			'The priority-1 task should be picked next once the priority-0 task is consumed.'
		);
	}
}