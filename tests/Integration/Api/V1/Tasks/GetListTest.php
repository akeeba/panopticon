<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Tasks;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Task\GetList;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/tasks.
 *
 * @since  1.4.0
 */
class GetListTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Task\\GetList');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuperWithoutSiteFilter(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(GetList::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testForbiddenForNonSuperWithUnauthorisedSiteFilter(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(GetList::class, ['site_id' => '999999']);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testSuperUserGetsListWithPagination(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(GetList::class, ['limit' => 10, 'offset' => 0]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertIsArray($response['body']['data']);
		$this->assertArrayHasKey('pagination', $response['body']);
		$this->assertArrayHasKey('total', $response['body']['pagination']);
		$this->assertSame(10, $response['body']['pagination']['limit']);
		$this->assertSame(0, $response['body']['pagination']['offset']);
	}
}
