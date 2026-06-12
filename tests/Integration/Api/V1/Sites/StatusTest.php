<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/site/:id/status.
 *
 * Verifies:
 * - Authentication and scope enforcement (401, 403).
 * - 404 for unknown site id.
 * - The response shape: all eight health areas present, each with a valid status string.
 * - Status rules for areas that derive purely from locally-cached config values:
 *   cms_update, template_overrides, php, server, extensions, backup (not-linked path),
 *   file_scanner (not-installed path), core_checksums.
 *
 * Areas that require a live HTTP call to the remote site (backup OK/error via Akeeba Backup
 * Pro, file_scanner OK/error via Admin Tools Pro) are not exercised end-to-end because they
 * would need a real or stubbed remote endpoint; the "service not linked/installed" branches
 * are covered here instead.
 *
 * @since  1.6.2
 */
class StatusTest extends AbstractApiIntegrationTestCase
{
	// ── Helper ──────────────────────────────────────────────────────────────

	/**
	 * Insert a site with the given config array and return it.
	 *
	 * @param   array  $config  The config values; will be json_encoded and stored in the `config` column.
	 *
	 * @return  Site
	 */
	private function makeSite(array $config = [], ?string $name = null, string $url = 'https://status-test.example/'): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => $name ?? ('Status test ' . bin2hex(random_bytes(3))),
			'url'     => $url,
			'enabled' => 1,
			'config'  => empty($config) ? null : json_encode($config),
		]);

		return $site;
	}

	// ── Authentication & authorisation ──────────────────────────────────────

	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\Status', ['id' => 1]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownId(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Status::class, ['id' => 999999999]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenForUserWithoutSiteAccess(): void
	{
		// A super user creates the site so there is a valid id to reference.
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());
		$site = $this->makeSite();

		// A non-super user with no panopticon.read ACL on that site should get 403.
		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testForbiddenWithMissingSitesReadScope(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->injectTokenScopes(['tasks:read']);

		$site = $this->makeSite();

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.scope_forbidden', $response['body']['code']);
	}

	// ── Response shape ──────────────────────────────────────────────────────

	public function testResponseShapeHasAllEightAreas(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);

		$data = $response['body']['data'];
		$this->assertSame((int) $site->getId(), $data['id']);
		$this->assertArrayHasKey('areas', $data);

		$expectedAreas = [
			'cms_update', 'template_overrides', 'php', 'server',
			'extensions', 'backup', 'file_scanner', 'core_checksums',
		];
		$validStatuses = ['ok', 'warning', 'error', 'unknown'];

		foreach ($expectedAreas as $area)
		{
			$this->assertArrayHasKey($area, $data['areas'], "Area absent: {$area}");
			$this->assertArrayHasKey('status', $data['areas'][$area], "{$area}.status absent");
			$this->assertArrayHasKey('detail', $data['areas'][$area], "{$area}.detail absent");
			$this->assertContains(
				$data['areas'][$area]['status'],
				$validStatuses,
				"{$area}.status must be ok|warning|error|unknown"
			);
		}
	}

	// ── cms_update area ─────────────────────────────────────────────────────

	public function testCmsUpdateStatusIsUnknownWhenNoVersionData(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// No core.current.version in config → should return unknown.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['cms_update'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('no_data', $area['detail']['reason']);
	}

	public function testCmsUpdateStatusIsOkWhenCurrentAndNoUpgrade(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Use a Joomla site with a fictional version ("5.99.0") that will not match any
		// entry in the live EOL database, so both isEOLMajor() and isEOLBranch() return
		// false, letting the canUpgrade flag determine the final status.
		$site = $this->makeSite([
			'core' => [
				'current'             => ['version' => '5.99.0'],
				'latest'              => ['version' => '5.99.0'],
				'canUpgrade'          => false,
				'extensionAvailable'  => true,
				'updateSiteAvailable' => true,
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['cms_update'];
		$this->assertSame('ok', $area['status']);
		$this->assertFalse($area['detail']['can_upgrade']);
	}

	public function testCmsUpdateStatusIsWarningWhenUpgradeAvailable(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Same fictional version trick; canUpgrade = true pushes it to warning.
		$site = $this->makeSite([
			'core' => [
				'current'             => ['version' => '5.98.0'],
				'latest'              => ['version' => '5.99.0'],
				'canUpgrade'          => true,
				'extensionAvailable'  => true,
				'updateSiteAvailable' => true,
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['cms_update'];
		$this->assertSame('warning', $area['status']);
		$this->assertTrue($area['detail']['can_upgrade']);
	}

	// ── template_overrides area ─────────────────────────────────────────────

	public function testTemplateOverridesIsUnknownForWordPressSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site     = $this->makeSite(['cmsType' => 'wordpress']);
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['template_overrides'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('wordpress_not_applicable', $area['detail']['reason']);
	}

	public function testTemplateOverridesIsOkWhenNoChangedOverrides(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site = $this->makeSite([
			'core' => ['overridesChanged' => 0],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['template_overrides'];
		$this->assertSame('ok', $area['status']);
		$this->assertSame(0, $area['detail']['changed_count']);
	}

	public function testTemplateOverridesIsWarningWhenOverridesChanged(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site = $this->makeSite([
			'core' => ['overridesChanged' => 4],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['template_overrides'];
		$this->assertSame('warning', $area['status']);
		$this->assertSame(4, $area['detail']['changed_count']);
	}

	// ── core_checksums area ─────────────────────────────────────────────────

	public function testCoreChecksumsIsUnknownWhenNeverRunOnWordPressSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site     = $this->makeSite(['cmsType' => 'wordpress']);
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['core_checksums'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('never_run', $area['detail']['reason']);
	}

	public function testCoreChecksumsIsUnknownWhenNeverRun(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// Joomla site with no coreChecksums data at all.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['core_checksums'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('never_run', $area['detail']['reason']);
	}

	public function testCoreChecksumsIsOkWhenLastStatusTrue(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site = $this->makeSite([
			'core' => [
				'coreChecksums' => [
					'lastCheck'     => time(),
					'lastStatus'    => true,
					'modifiedCount' => 0,
				],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['core_checksums'];
		$this->assertSame('ok', $area['status']);
		$this->assertTrue($area['detail']['last_status']);
		$this->assertSame(0, $area['detail']['modified_count']);
	}

	public function testCoreChecksumsIsWarningWhenModifiedFilesFound(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site = $this->makeSite([
			'core' => [
				'coreChecksums' => [
					'lastCheck'     => time(),
					'lastStatus'    => false,
					'modifiedCount' => 3,
				],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['core_checksums'];
		$this->assertSame('warning', $area['status']);
		$this->assertFalse($area['detail']['last_status']);
		$this->assertSame(3, $area['detail']['modified_count']);
	}

	// ── extensions area ─────────────────────────────────────────────────────

	public function testExtensionsIsUnknownWhenNoLastAttempt(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// No extensions.lastAttempt → unknown.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['extensions'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('no_data', $area['detail']['reason']);
	}

	public function testExtensionsIsOkWhenNoIssues(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$site = $this->makeSite([
			'extensions' => [
				'lastAttempt' => time(),
				'list'        => [],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['extensions'];
		$this->assertSame('ok', $area['status']);
		$this->assertSame(0, $area['detail']['updates_count']);
		$this->assertSame(0, $area['detail']['missing_keys_count']);
		$this->assertSame(0, $area['detail']['missing_sites_count']);
	}

	// ── backup area ─────────────────────────────────────────────────────────

	public function testBackupIsUnknownWhenAkeebaBackupNotLinked(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// No akeebabackup.info.api in config → $hasPro = false → unknown.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['backup'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('not_linked', $area['detail']['reason']);
	}

	// ── file_scanner area ────────────────────────────────────────────────────

	public function testFileScannerIsUnknownWhenAdminToolsNotInstalled(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// No pkg_admintools in extensions.list → hasAdminToolsPro() returns false → unknown.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['file_scanner'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('not_installed', $area['detail']['reason']);
	}

	// ── server area ─────────────────────────────────────────────────────────

	public function testServerIsUnknownWhenNoServerInfoCollected(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// No core.serverInfo → unknown.
		$site     = $this->makeSite();
		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['server'];
		$this->assertSame('unknown', $area['status']);
		$this->assertSame('no_data', $area['detail']['reason']);
	}

	public function testServerIsOkWhenMetricsWithinThresholds(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// RAM 40% used (well below 70% warning threshold), disk free 50%.
		$site = $this->makeSite([
			'core' => [
				'serverInfo' => [
					'memory'   => ['used' => 4000, 'cache' => 1000, 'free' => 5000, 'total' => 10000],
					'siteDisk' => ['total' => 100, 'free' => 50],
					'dbDisk'   => ['total' => 0, 'free' => 0],
					'cpuUsage' => ['iowait' => '0.10'],
				],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['server'];
		$this->assertSame('ok', $area['status']);
		$this->assertEqualsWithDelta(40.0, $area['detail']['ram_used_pct'], 0.1);
		$this->assertEqualsWithDelta(50.0, $area['detail']['site_disk_free_pct'], 0.1);
	}

	public function testServerIsWarningWhenRamUsageHigh(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// RAM 75% used — above 70% warning threshold, below 85% error threshold.
		$site = $this->makeSite([
			'core' => [
				'serverInfo' => [
					'memory'   => ['used' => 7500, 'cache' => 0, 'free' => 2500, 'total' => 10000],
					'siteDisk' => ['total' => 100, 'free' => 50],
					'dbDisk'   => ['total' => 0, 'free' => 0],
					'cpuUsage' => ['iowait' => '0.10'],
				],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['server'];
		$this->assertSame('warning', $area['status']);
	}

	public function testServerIsErrorWhenRamUsageCritical(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		// RAM 90% used — above 85% error threshold.
		$site = $this->makeSite([
			'core' => [
				'serverInfo' => [
					'memory'   => ['used' => 9000, 'cache' => 0, 'free' => 1000, 'total' => 10000],
					'siteDisk' => ['total' => 100, 'free' => 50],
					'dbDisk'   => ['total' => 0, 'free' => 0],
					'cpuUsage' => ['iowait' => '0.10'],
				],
			],
		]);

		$response = $this->invokeHandler(Status::class, ['id' => (int) $site->getId()]);

		$area = $response['body']['data']['areas']['server'];
		$this->assertSame('error', $area['status']);
	}
}
