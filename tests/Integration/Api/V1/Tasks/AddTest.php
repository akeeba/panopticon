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
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for PUT /v1/task.
 *
 * @since  1.4.0
 */
class AddTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody([
			'type'            => 'logrotate',
			'cron_expression' => '@daily',
		]);

		$response = $this->dispatchApi('V1\\Task\\Add');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuperSystemTask(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody([
			'type'            => 'logrotate',
			'cron_expression' => '@daily',
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testBadRequestWhenTypeMissing(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['cron_expression' => '@daily']);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testBadRequestWhenCronMissing(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['type' => 'logrotate']);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testUnknownTypeReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody([
			'type'            => 'this_task_type_does_not_exist',
			'cron_expression' => '@daily',
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(422, $response['status']);
		$this->assertSame('task.unknown_type', $response['body']['code']);
	}

	public function testInvalidCronReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Pick a known task type from the registry.
		$type = $this->pickKnownTaskType();
		$this->setJsonRequestBody([
			'type'            => $type,
			'cron_expression' => 'this is not a cron expression',
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(422, $response['status']);
		$this->assertSame('task.invalid_cron', $response['body']['code']);
	}

	public function testHappyPathReturns201(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$type = $this->pickKnownTaskType();
		$this->setJsonRequestBody([
			'type'            => $type,
			'cron_expression' => '@daily',
			'enabled'         => false,
			'params'          => ['note' => 'added by test'],
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(201, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertArrayHasKey('id', $response['body']['data']);
		$this->assertSame($type, $response['body']['data']['type']);
		$this->assertSame('@daily', $response['body']['data']['cron_expression']);
		$this->assertFalse($response['body']['data']['enabled']);
	}

	private function pickKnownTaskType(): string
	{
		$registry = $this->container->taskRegistry;

		foreach ($registry->getIterator() as $type => $callback)
		{
			return (string) $type;
		}

		$this->markTestSkipped('No task types registered in the registry.');
	}
}
