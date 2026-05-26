<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Modify;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/site/:id.
 *
 * @since  1.4.0
 */
class ModifyTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody(['name' => 'x']);

		$response = $this->dispatchApi('V1\\Site\\Modify', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['name' => 'x']);

		$response = $this->invokeHandler(Modify::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenForUserWithoutAcl(): void
	{
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Modify ACL site',
			'url'     => 'https://acl.test/api',
			'enabled' => 1,
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());
		$this->setJsonRequestBody(['name' => 'renamed']);

		$response = $this->invokeHandler(Modify::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testBadRequestOnEmptyBody(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Empty body site',
			'url'     => 'https://empty.test/api',
			'enabled' => 1,
		]);

		$this->setJsonRequestBody('');

		$response = $this->invokeHandler(Modify::class, ['id' => (int) $site->getId()]);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testSuccessRenamesSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Before rename',
			'url'     => 'https://rename.test/api',
			'enabled' => 1,
		]);

		$this->setJsonRequestBody([
			'name'    => 'After rename',
			'enabled' => false,
		]);

		$response = $this->invokeHandler(Modify::class, ['id' => (int) $site->getId()]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertSame('After rename', $response['body']['data']['name']);
		$this->assertFalse($response['body']['data']['enabled']);
	}
}
