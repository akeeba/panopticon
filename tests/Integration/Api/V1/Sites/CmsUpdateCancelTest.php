<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\CmsUpdateCancel;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/site/:id/cmsupdate/cancel.
 *
 * @since  1.4.0
 */
class CmsUpdateCancelTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\CmsUpdateCancel', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(CmsUpdateCancel::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenWithoutRunPrivilege(): void
	{
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'CmsCancel ACL site',
			'url'     => 'https://cmscancel-acl.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(CmsUpdateCancel::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testNoScheduledTaskReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'CmsCancel no task',
			'url'     => 'https://cmscancel-none.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$response = $this->invokeHandler(CmsUpdateCancel::class, ['id' => (int) $site->getId()]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('task.not_scheduled', $response['body']['code']);
	}

	public function testWrongCmsReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'CmsCancel wrong CMS',
			'url'     => 'https://cmscancel-wrong.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'unknown']),
		]);

		$response = $this->invokeHandler(CmsUpdateCancel::class, ['id' => (int) $site->getId()]);

		$this->assertSame(422, $response['status']);
		$this->assertSame('site.wrong_cms', $response['body']['code']);
	}
}
