<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Add;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for PUT /v1/site.
 *
 * @since  1.4.0
 */
class AddTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody(['name' => 'x', 'url' => 'https://x.test/api']);

		$response = $this->dispatchApi('V1\\Site\\Add');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForUserWithoutPrivilege(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody([
			'name' => 'Test',
			'url'  => 'https://test.local/api',
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testBadRequestWhenNameMissing(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['url' => 'https://test.local/api']);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testBadRequestWhenUrlMissing(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['name' => 'No url']);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testSuccessReturns201AndCreatedSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$name = 'Test site ' . bin2hex(random_bytes(3));

		$this->setJsonRequestBody([
			'name'    => $name,
			'url'     => 'https://example.test/api',
			'enabled' => true,
			'notes'   => 'A note',
			'config'  => [
				'config' => [
					'cmsType' => 'joomla',
					'apiKey'  => 'SECRET-ROUND-TRIP',
				],
			],
		]);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(201, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertSame($name, $response['body']['data']['name']);
		$this->assertSame('joomla', $response['body']['data']['cmsType']);
		// No redaction.
		$this->assertStringContainsString(
			'SECRET-ROUND-TRIP',
			json_encode($response['body']['data']['config'])
		);
	}
}
