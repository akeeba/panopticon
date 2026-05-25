<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\ExtensionCancelUpdate;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/site/:id/extensions/cancel/:extId.
 *
 * @since  1.4.0
 */
class ExtensionCancelUpdateTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\ExtensionCancelUpdate', ['id' => 1, 'extId' => 42]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(ExtensionCancelUpdate::class, ['id' => 999999999, 'extId' => 42]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenWithoutRun(): void
	{
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'ExtCancel ACL site',
			'url'     => 'https://extcancel-acl.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(
			ExtensionCancelUpdate::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testBadRequestForZeroExtId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'ExtCancel bad extId',
			'url'     => 'https://extcancel-bad.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$response = $this->invokeHandler(
			ExtensionCancelUpdate::class,
			['id' => (int) $site->getId(), 'extId' => 0]
		);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testNotInQueueReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'ExtCancel no-queue',
			'url'     => 'https://extcancel-noqueue.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$response = $this->invokeHandler(
			ExtensionCancelUpdate::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(404, $response['status']);
		$this->assertSame('task.not_scheduled', $response['body']['code']);
	}
}
