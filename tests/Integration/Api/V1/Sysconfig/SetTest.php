<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Sysconfig\Set;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/sysconfig/:paramName.
 *
 * NOTE: a happy-path test that actually persists a new value is intentionally
 * skipped — it would mutate `config.php` outside the per-test rollback. We cover
 * every failure path instead.
 *
 * @since  1.4.0
 */
class SetTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody(['value' => true]);

		$response = $this->dispatchApi('V1\\Sysconfig\\Set', ['paramName' => 'debug']);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuper(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['value' => true]);

		$response = $this->invokeHandler(Set::class, ['paramName' => 'debug']);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testForbiddenForSensitiveKey(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['value' => 'whatever']);

		$response = $this->invokeHandler(Set::class, ['paramName' => 'dbpass']);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testUnknownKeyReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['value' => 'x']);

		$response = $this->invokeHandler(Set::class, ['paramName' => 'no_such_key_exists_here']);

		$this->assertSame(404, $response['status']);
		$this->assertSame('sysconfig.unknown_param', $response['body']['code']);
	}

	public function testMissingValueReturns400(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody([]);

		$response = $this->invokeHandler(Set::class, ['paramName' => 'debug']);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testInvalidValueReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// session_timeout requires int > 1
		$this->setJsonRequestBody(['value' => 'not-an-int']);

		$response = $this->invokeHandler(Set::class, ['paramName' => 'session_timeout']);

		$this->assertSame(422, $response['status']);
		$this->assertSame('sysconfig.invalid_value', $response['body']['code']);
	}
}
