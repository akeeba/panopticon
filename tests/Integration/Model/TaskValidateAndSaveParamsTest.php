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

/**
 * Tests that {@see Task::validateAndSave()} persists `params` and `storage` (gh-1089).
 *
 * The method used to coerce them into Registry objects which the DataModel then wrote out as NULL.
 */
class TaskValidateAndSaveParamsTest extends AbstractIntegrationTestCase
{
	private const PARAMS = [
		'sites'           => [1],
		'url'             => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
		'initiating_user' => 1,
		'run_once'        => 'delete',
	];

	protected function setUp(): void
	{
		parent::setUp();

		$db = $this->container->db;
		$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__tasks')))->execute();

		// Self-contained: do not depend on which task types other tests left in the shared registry.
		$this->container->taskRegistry->add('test_params', new class implements CallbackInterface
		{
			public function __invoke(object $task, Registry $storage): int
			{
				return Status::OK->value;
			}

			public function getTaskType(): string
			{
				return 'test_params';
			}

			public function getDescription(): string
			{
				return 'Test params persistence callback';
			}
		});
	}

	private function rawColumn(int $id, string $column): ?string
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->quoteName($column))
			->from($db->quoteName('#__tasks'))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$value = $db->setQuery($query)->loadResult();

		return $value === null ? null : (string) $value;
	}

	private function create(array $extra): Task
	{
		$model = new Task($this->container);
		$model->setState('disable_next_execution_recalculation', true);
		$model->validateAndSave(
			array_merge(
				[
					'type'            => 'test_params',
					'cron_expression' => '* * * * *',
					'enabled'         => 1,
				],
				$extra
			)
		);

		return $model;
	}

	public function testCreatePersistsParamsGivenAsArray(): void
	{
		$task = $this->create(['params' => self::PARAMS, 'storage' => '{}']);

		$raw = $this->rawColumn($task->getId(), 'params');

		$this->assertNotNull($raw, 'params must not be stored as NULL');
		$this->assertEquals(self::PARAMS, json_decode($raw, true));
	}

	public function testCreatePersistsParamsGivenAsJsonString(): void
	{
		$task = $this->create(['params' => json_encode(self::PARAMS), 'storage' => '{}']);

		$raw = $this->rawColumn($task->getId(), 'params');

		$this->assertNotNull($raw, 'params must not be stored as NULL');
		$this->assertEquals(self::PARAMS, json_decode($raw, true));
	}

	public function testModifyPersistsParamsAndStorage(): void
	{
		$task = $this->create(['params' => [], 'storage' => '{}']);

		$model = new Task($this->container);
		$model->findOrFail($task->getId());
		$model->setState('disable_next_execution_recalculation', true);
		$model->validateAndSave(['params' => self::PARAMS, 'storage' => ['currentIndex' => 2]]);

		$params  = $this->rawColumn($task->getId(), 'params');
		$storage = $this->rawColumn($task->getId(), 'storage');

		$this->assertNotNull($params, 'params must not be stored as NULL');
		$this->assertEquals(self::PARAMS, json_decode($params, true));
		$this->assertNotNull($storage, 'storage must not be stored as NULL');
		$this->assertEquals(['currentIndex' => 2], json_decode($storage, true));
	}
}
