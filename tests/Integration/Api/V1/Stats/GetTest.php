<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Stats;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Stats\Get;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/stats.
 *
 * Verifies authentication enforcement, scope enforcement, the response envelope shape, and
 * that the aggregate SQL counters correctly reflect data inserted within the test transaction.
 *
 * @since  1.6.2
 */
class GetTest extends AbstractApiIntegrationTestCase
{
	// ── Authentication & authorisation ──────────────────────────────────────

	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Stats\\Get');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuperUser(): void
	{
		// No panopticon.super — the endpoint requires it for global counters.
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testForbiddenWithMissingSitesReadScope(): void
	{
		// Super user but token scoped to tasks:read only — should be rejected.
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->injectTokenScopes(['tasks:read']);

		$response = $this->invokeHandler(Get::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.scope_forbidden', $response['body']['code']);
	}

	// ── Response shape ──────────────────────────────────────────────────────

	public function testReturnsCorrectShapeWithAllExpectedKeys(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Get::class);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);

		$data = $response['body']['data'];

		// Sites bucket must be present with all ten scalar integer fields.
		$this->assertArrayHasKey('sites', $data);
		$sitesKeys = [
			'total', 'enabled', 'with_cms_update', 'with_ext_updates',
			'backup_ok', 'backup_problem', 'core_checksums_ok', 'core_checksums_fail',
			'file_scanner_ok', 'file_scanner_fail',
		];
		foreach ($sitesKeys as $key)
		{
			$this->assertArrayHasKey($key, $data['sites'], "Missing sites key: {$key}");
			$this->assertIsInt($data['sites'][$key], "sites.{$key} must be int");
		}

		// Tasks bucket must be present with all four scalar integer fields.
		$this->assertArrayHasKey('tasks', $data);
		foreach (['total', 'pending', 'running', 'failed'] as $key)
		{
			$this->assertArrayHasKey($key, $data['tasks'], "Missing tasks key: {$key}");
			$this->assertIsInt($data['tasks'][$key], "tasks.{$key} must be int");
		}
	}

	// ── Counter accuracy ────────────────────────────────────────────────────

	public function testTotalSiteCountIncreasesWhenSiteInserted(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$before      = $this->invokeHandler(Get::class);
		$totalBefore = $before['body']['data']['sites']['total'];

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Stats test site ' . bin2hex(random_bytes(3)),
			'url'     => 'https://stats-test.example/',
			'enabled' => 1,
		]);

		$after = $this->invokeHandler(Get::class);
		$this->assertSame($totalBefore + 1, $after['body']['data']['sites']['total']);
	}

	public function testEnabledCountExcludesDisabledSites(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$before        = $this->invokeHandler(Get::class);
		$totalBefore   = $before['body']['data']['sites']['total'];
		$enabledBefore = $before['body']['data']['sites']['enabled'];

		// Insert one enabled and one disabled site.
		foreach ([1, 0] as $enabled)
		{
			/** @var Site $site */
			$site = $this->container->mvcFactory->makeTempModel('Site');
			$site->save([
				'name'    => 'Enabled=' . $enabled . ' site ' . bin2hex(random_bytes(3)),
				'url'     => 'https://enabled-' . $enabled . '.example/',
				'enabled' => $enabled,
			]);
		}

		$after = $this->invokeHandler(Get::class);
		$this->assertSame($totalBefore + 2, $after['body']['data']['sites']['total']);
		$this->assertSame($enabledBefore + 1, $after['body']['data']['sites']['enabled']);
	}

	public function testWithCmsUpdateCountIncrementsForSiteWithCanUpgrade(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$before = $this->invokeHandler(Get::class);
		$cmsUpdatesBefore = $before['body']['data']['sites']['with_cms_update'];

		// Insert an enabled site with core.canUpgrade = true.
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'CMS upgrade site ' . bin2hex(random_bytes(3)),
			'url'     => 'https://cms-upgrade.example/',
			'enabled' => 1,
			'config'  => json_encode(['core' => ['canUpgrade' => true]]),
		]);

		$after = $this->invokeHandler(Get::class);
		$this->assertSame($cmsUpdatesBefore + 1, $after['body']['data']['sites']['with_cms_update']);
	}

	public function testCoreChecksumsCountsReflectConfigFlags(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$before     = $this->invokeHandler(Get::class);
		$okBefore   = $before['body']['data']['sites']['core_checksums_ok'];
		$failBefore = $before['body']['data']['sites']['core_checksums_fail'];

		$now = time();

		// Site with lastStatus = true → should land in core_checksums_ok.
		/** @var Site $siteOk */
		$siteOk = $this->container->mvcFactory->makeTempModel('Site');
		$siteOk->save([
			'name'    => 'Checksums ok ' . bin2hex(random_bytes(3)),
			'url'     => 'https://checksums-ok.example/',
			'enabled' => 1,
			'config'  => json_encode([
				'core' => [
					'coreChecksums' => [
						'lastCheck'  => $now,
						'lastStatus' => true,
					],
				],
			]),
		]);

		// Site with lastStatus = false → should land in core_checksums_fail.
		/** @var Site $siteFail */
		$siteFail = $this->container->mvcFactory->makeTempModel('Site');
		$siteFail->save([
			'name'    => 'Checksums fail ' . bin2hex(random_bytes(3)),
			'url'     => 'https://checksums-fail.example/',
			'enabled' => 1,
			'config'  => json_encode([
				'core' => [
					'coreChecksums' => [
						'lastCheck'  => $now,
						'lastStatus' => false,
					],
				],
			]),
		]);

		$after = $this->invokeHandler(Get::class);
		$this->assertSame($okBefore + 1, $after['body']['data']['sites']['core_checksums_ok']);
		$this->assertSame($failBefore + 1, $after['body']['data']['sites']['core_checksums_fail']);
	}
}
