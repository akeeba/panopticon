<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Refresh;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * Integration tests for POST /v1/site/:id/refresh.
 *
 * The refresh callback is stubbed so the tests never touch the network.
 *
 * @since  1.4.0
 */
class RefreshTest extends AbstractApiIntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Stub the `refreshsiteinfo` task with a no-op that returns OK immediately.
		$stub = new class implements CallbackInterface {
			public function __invoke(object $task, Registry $storage): int
			{
				return Status::OK->value;
			}
		};

		$this->container->taskRegistry->add('refreshsiteinfo', $stub);
	}

	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\Refresh', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Refresh::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenWithoutReadPrivilege(): void
	{
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Refresh ACL site',
			'url'     => 'https://refresh.test/api',
			'enabled' => 1,
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(Refresh::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testSuccessReturns200(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Refresh ok site',
			'url'     => 'https://refresh-ok.test/api',
			'enabled' => 1,
		]);

		$response = $this->invokeHandler(Refresh::class, ['id' => (int) $site->getId()]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
	}
}
