<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Sysconfig\GetList;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/sysconfig.
 *
 * @since  1.4.0
 */
class GetListTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Sysconfig\\GetList');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuper(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(GetList::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testSensitiveKeysAreAbsentFromList(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(GetList::class);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertIsArray($response['body']['data']);

		// Sensitive keys MUST be completely absent.
		$this->assertArrayNotHasKey('dbpass', $response['body']['data']);
		$this->assertArrayNotHasKey('secret', $response['body']['data']);
		$this->assertArrayNotHasKey('smtppass', $response['body']['data']);
	}
}
