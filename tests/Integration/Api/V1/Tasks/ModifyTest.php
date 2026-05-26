<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Tasks;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Task\Add;
use Akeeba\Panopticon\Controller\Api\V1\Task\Modify;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/task/:id.
 *
 * @since  1.4.0
 */
class ModifyTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody(['enabled' => false]);

		$response = $this->dispatchApi('V1\\Task\\Modify', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['enabled' => false]);

		$response = $this->invokeHandler(Modify::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('task.not_found', $response['body']['code']);
	}

	public function testBadRequestForNoFields(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Create a task first.
		$id = $this->createTaskAndGetId($user);

		$this->setJsonRequestBody([]);

		$response = $this->invokeHandler(Modify::class, ['id' => $id]);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testInvalidCronOnModifyReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$id = $this->createTaskAndGetId($user);

		$this->setJsonRequestBody(['cron_expression' => 'still not valid']);

		$response = $this->invokeHandler(Modify::class, ['id' => $id]);

		$this->assertSame(422, $response['status']);
		$this->assertSame('task.invalid_cron', $response['body']['code']);
	}

	public function testHappyPath(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$id = $this->createTaskAndGetId($user);

		$this->setJsonRequestBody(['enabled' => false, 'cron_expression' => '@weekly']);

		$response = $this->invokeHandler(Modify::class, ['id' => $id]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertSame($id, $response['body']['data']['id']);
		$this->assertFalse($response['body']['data']['enabled']);
		$this->assertSame('@weekly', $response['body']['data']['cron_expression']);
	}

	private function createTaskAndGetId(\Awf\User\User $user): int
	{
		$type = null;

		foreach ($this->container->taskRegistry->getIterator() as $t => $_)
		{
			$type = (string) $t;
			break;
		}

		if ($type === null)
		{
			$this->markTestSkipped('No task types registered.');
		}

		$this->setJsonRequestBody([
			'type'            => $type,
			'cron_expression' => '@daily',
			'enabled'         => true,
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(201, $response['status'], 'Could not create task to modify: ' . json_encode($response['body']));

		return (int) $response['body']['data']['id'];
	}
}
