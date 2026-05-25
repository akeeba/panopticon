<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Get;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/site/:id.
 *
 * @since  1.4.0
 */
class GetTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\Get', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testSuccessReturnsFullConfigUnredacted(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Create a site with a sensitive value in config to round-trip-test the
		// "no redaction" policy (master plan decision #8).
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Test site ' . bin2hex(random_bytes(3)),
			'url'     => 'https://example.test/',
			'enabled' => 1,
			'config'  => json_encode([
				'config' => [
					'downloadkey' => 'SECRET-DOWNLOAD-KEY-MUST-SURVIVE',
				],
			]),
		]);

		$response = $this->invokeHandler(Get::class, ['id' => (int) $site->getId()]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertSame((int) $site->getId(), $response['body']['data']['id']);
		$this->assertArrayHasKey('config', $response['body']['data']);

		// Re-encode for substring search across nested config keys (no redaction).
		$serialisedConfig = json_encode($response['body']['data']['config']);
		$this->assertStringContainsString('SECRET-DOWNLOAD-KEY-MUST-SURVIVE', $serialisedConfig);
	}

	public function testForbiddenForUserWithoutAcl(): void
	{
		// Owner creates the site.
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Owned by super',
			'url'     => 'https://owned.test/',
			'enabled' => 1,
		]);

		// Non-super user with no ACL grants tries to read it.
		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(Get::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}
}
