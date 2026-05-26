<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Sysconfig\Get;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/sysconfig/:paramName.
 *
 * @since  1.4.0
 */
class GetTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Sysconfig\\Get', ['paramName' => 'timezone']);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuper(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class, ['paramName' => 'timezone']);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testHappyPathReturnsKnownKey(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class, ['paramName' => 'timezone']);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertArrayHasKey('timezone', $response['body']['data']);
	}

	public function testUnknownKeyReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class, ['paramName' => 'this_key_does_not_exist']);

		$this->assertSame(404, $response['status']);
		$this->assertSame('sysconfig.unknown_param', $response['body']['code']);
	}

	public function testSensitiveKeyReturns404NotLeaked(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// `dbpass` is a known KEY but sensitive — it must look identical to an unknown key.
		$response = $this->invokeHandler(Get::class, ['paramName' => 'dbpass']);

		$this->assertSame(404, $response['status']);
		$this->assertSame('sysconfig.unknown_param', $response['body']['code']);
		// The response body must not contain the actual value of the DB password.
		$this->assertStringNotContainsString('password', (string) ($response['body']['data'] ?? ''));
	}

	public function testMissingParamReturns400(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class, ['paramName' => '']);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}
}
