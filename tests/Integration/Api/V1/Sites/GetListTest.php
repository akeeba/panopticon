<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\GetList;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/sites.
 *
 * @since  1.4.0
 */
class GetListTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		// No Authorization header set: dispatcher must return 401.
		$response = $this->dispatchApi('V1\\Site\\GetList');

		$this->assertSame(401, $response['status']);
		$this->assertIsArray($response['body']);
		$this->assertFalse($response['body']['success']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testUnauthorisedWithInvalidToken(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer this-is-not-a-valid-token';

		$response = $this->dispatchApi('V1\\Site\\GetList');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testSuccessReturnsEnvelopeWithPagination(): void
	{
		$user  = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$token = $this->createApiToken((int) $user->getId());

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token['token'];

		$response = $this->invokeHandlerAuthenticated(
			GetList::class,
			(int) $user->getId(),
			['limit' => 10, 'offset' => 0]
		);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertIsArray($response['body']['data']);
		$this->assertArrayHasKey('pagination', $response['body']);
		$this->assertSame(10, $response['body']['pagination']['limit']);
		$this->assertSame(0, $response['body']['pagination']['offset']);
		$this->assertArrayHasKey('total', $response['body']['pagination']);
	}

	public function testPaginationLimitAndOffsetHonoured(): void
	{
		$user  = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$token = $this->createApiToken((int) $user->getId());

		$response = $this->invokeHandlerAuthenticated(
			GetList::class,
			(int) $user->getId(),
			['limit' => 5, 'offset' => 20]
		);

		$this->assertSame(200, $response['status']);
		$this->assertSame(5, $response['body']['pagination']['limit']);
		$this->assertSame(20, $response['body']['pagination']['offset']);
	}

	public function testInvalidCmsTypeRejected(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->createApiToken((int) $user->getId());

		$response = $this->invokeHandlerAuthenticated(
			GetList::class,
			(int) $user->getId(),
			['cmsType' => 'no-such-cms']
		);

		$this->assertSame(400, $response['status']);
		$this->assertFalse($response['body']['success']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	/**
	 * Convenience: log a user in (skipping the token-extraction round-trip) and invoke
	 * the handler directly. Authentication is exercised separately via dispatchApi().
	 */
	private function invokeHandlerAuthenticated(string $handlerClass, int $userId, array $input = []): array
	{
		$this->loginAs($userId);

		return $this->invokeHandler($handlerClass, $input);
	}
}
