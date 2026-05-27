<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\HealthStatus;
use Akeeba\Panopticon\Library\SoftwareVersions\JoomlaVersion;
use Akeeba\Panopticon\Library\SoftwareVersions\PhpVersion;
use Akeeba\Panopticon\Library\SoftwareVersions\WordPressVersion;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\AkeebaBackupTooOldTrait;
use Awf\Registry\Registry;

/**
 * API handler for GET /v1/site/:id/status — per-site health summary.
 *
 * Returns a structured health status for each monitored area of a site: CMS updates, PHP,
 * server resources, extensions, backup, file scanner, and core-file checksums. Each area
 * exposes a `status` field using the four-value {@see HealthStatus} enum and a `detail`
 * sub-object with raw values so clients can render fine-grained information.
 *
 * The shape of the response is intentionally stable; areas that do not apply to the site's CMS
 * (e.g. `template_overrides` and `core_checksums` on WordPress) are still present with
 * `status: "unknown"` and an explanatory `detail.reason` string.
 *
 * ACL: same gate as GET /api/v1/site/:id — requires the `sites:read` scope and either
 * `panopticon.super` or `panopticon.read` on the site.
 *
 * @since  1.6.2
 */
class Status extends AbstractApiHandler
{
	use AkeebaBackupTooOldTrait;

	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesRead);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');

		$config  = $site->getConfig();
		$cmsType = $site->cmsType();

		$this->sendJsonResponse([
			'id'      => (int) $site->getId(),
			'name'    => $site->name,
			'enabled' => (bool) $site->enabled,
			'areas'   => [
				'cms_update'         => $this->getCmsUpdateStatus($site, $config, $cmsType),
				'template_overrides' => $this->getTemplateOverridesStatus($config, $cmsType),
				'php'                => $this->getPhpStatus($config),
				'server'             => $this->getServerStatus($config),
				'extensions'         => $this->getExtensionsStatus($site, $config),
				'backup'             => $this->getBackupStatus($site, $config),
				'file_scanner'       => $this->getFileScannerStatus($site),
				'core_checksums'     => $this->getCoreChecksumsStatus($config, $cmsType),
			],
		]);
	}

	// ── Private helpers ────────────────────────────────────────────────────────

	/**
	 * Build a status area response array.
	 *
	 * @param   HealthStatus  $status  The computed status.
	 * @param   array         $detail  Raw values for machine consumption.
	 *
	 * @return  array{status: string, detail: array}
	 * @since   1.6.2
	 */
	private function area(HealthStatus $status, array $detail = []): array
	{
		return [
			'status' => $status->value,
			'detail' => $detail,
		];
	}

	/**
	 * CMS update status.
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | Version current, not EOL |
	 * | warning | Update available and can upgrade |
	 * | error   | EOL branch/major OR extension/update-site missing |
	 * | unknown | No version data collected yet |
	 *
	 * @since  1.6.2
	 */
	private function getCmsUpdateStatus(Site $site, Registry $config, CMSType $cmsType): array
	{
		$currentVersion = $config->get('core.current.version');
		$latestVersion  = $config->get('core.latest.version');
		$canUpgrade     = (bool) $config->get('core.canUpgrade', false);

		if (empty($currentVersion))
		{
			return $this->area(HealthStatus::Unknown, [
				'reason'          => 'no_data',
				'current_version' => null,
				'latest_version'  => null,
				'can_upgrade'     => false,
			]);
		}

		$detail = [
			'current_version' => $currentVersion,
			'latest_version'  => $latestVersion,
			'can_upgrade'     => $canUpgrade,
		];

		if ($cmsType === CMSType::JOOMLA)
		{
			$jVersionHelper = new JoomlaVersion($this->container);

			$extensionAvailable = (bool) $config->get('core.extensionAvailable', true);
			$updateSiteAvailable = (bool) $config->get('core.updateSiteAvailable', true);
			$versionInfo        = $jVersionHelper->getVersionInformation($currentVersion);

			$detail['eol']                 = $versionInfo->eol ?? false;
			$detail['eol_branch']          = $versionInfo->eolBranch ?? false;
			$detail['security_only']       = $versionInfo->security ?? false;
			$detail['extension_available'] = $extensionAvailable;
			$detail['update_site_ok']      = $updateSiteAvailable;

			if (!$extensionAvailable || !$updateSiteAvailable || $jVersionHelper->isEOLMajor($currentVersion) || $jVersionHelper->isEOLBranch($currentVersion))
			{
				return $this->area(HealthStatus::Error, $detail);
			}
		}
		elseif ($cmsType === CMSType::WORDPRESS)
		{
			$wpVersionHelper = new WordPressVersion($this->container);
			$versionInfo     = $wpVersionHelper->getVersionInformation($currentVersion);

			$detail['eol'] = $versionInfo->eol ?? false;

			if ($wpVersionHelper->isEOL($currentVersion))
			{
				return $this->area(HealthStatus::Error, $detail);
			}
		}

		if ($canUpgrade)
		{
			return $this->area(HealthStatus::Warning, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * Template overrides status (Joomla only).
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | No changed overrides |
	 * | warning | One or more changed overrides |
	 * | unknown | Not Joomla, or no data collected yet |
	 *
	 * @since  1.6.2
	 */
	private function getTemplateOverridesStatus(Registry $config, CMSType $cmsType): array
	{
		if ($cmsType !== CMSType::JOOMLA)
		{
			return $this->area(HealthStatus::Unknown, [
				'reason' => 'wordpress_not_applicable',
			]);
		}

		$overridesChanged = $config->get('core.overridesChanged');

		if ($overridesChanged === null)
		{
			return $this->area(HealthStatus::Unknown, [
				'reason'        => 'no_data',
				'changed_count' => null,
			]);
		}

		$changedCount = (int) $overridesChanged;

		if ($changedCount > 0)
		{
			return $this->area(HealthStatus::Warning, ['changed_count' => $changedCount]);
		}

		return $this->area(HealthStatus::Ok, ['changed_count' => 0]);
	}

	/**
	 * PHP version health status.
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | Not EOL, not security-only, running latest patch in branch |
	 * | warning | Security-only branch OR patch update available within branch |
	 * | error   | EOL version |
	 * | unknown | No PHP version data collected |
	 *
	 * @since  1.6.2
	 */
	private function getPhpStatus(Registry $config): array
	{
		$php = $config->get('core.php', '0.0.0') ?: '0.0.0';

		if ($php === '0.0.0')
		{
			return $this->area(HealthStatus::Unknown, [
				'reason'  => 'no_data',
				'version' => null,
			]);
		}

		$phpVersion            = new PhpVersion($this->container);
		$versionInfo           = $phpVersion->getVersionInformation($php);
		$latestInBranch        = $versionInfo?->latest;
		$isLatestPatch         = version_compare($php, $latestInBranch ?? '0.0.0', 'ge');

		$detail = [
			'version'            => $php,
			'eol'                => $versionInfo?->eol ?? true,
			'security_only'      => $versionInfo?->security ?? false,
			'latest_in_branch'   => $latestInBranch,
			'is_latest_patch'    => $isLatestPatch,
		];

		// Unknown version (not in PHP's EOL database)
		if ($versionInfo?->unknown ?? false)
		{
			return $this->area(HealthStatus::Unknown, array_merge($detail, ['reason' => 'unknown_version']));
		}

		if ($phpVersion->isEOL($php))
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		// Security-only support OR patch update available within the branch
		if ($phpVersion->isSecurity($php) || !$isLatestPatch)
		{
			return $this->area(HealthStatus::Warning, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * Server resource health status.
	 *
	 * Thresholds match the Site Information page UI:
	 *  - Error  : RAM usage ≥ 85 %
	 *  - Warning: RAM usage ≥ 70 % OR disk free ≤ 10 % OR CPU I/O wait ≥ 5 %
	 *  - Ok     : all metrics within thresholds
	 *  - Unknown: server-info data not collected
	 *
	 * @since  1.6.2
	 */
	private function getServerStatus(Registry $config): array
	{
		$serverInfo = $config->get('core.serverInfo');

		if (empty($serverInfo))
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'no_data']);
		}

		// Memory
		$memUsed  = floatval($serverInfo->memory?->used ?? 0);
		$memCache = floatval($serverInfo->memory?->cache ?? 0);
		$memFree  = floatval($serverInfo->memory?->free ?? 0);
		$memTotal = floatval($serverInfo->memory?->total ?? 0) ?: ($memUsed + $memCache + $memFree);
		$ramUsedPct = $memTotal > 0 ? (100.0 * $memUsed / $memTotal) : 0.0;

		// Disk (site partition — primary metric)
		$siteDiskTotal = floatval($serverInfo->siteDisk->total ?? 0);
		$siteDiskFree  = floatval($serverInfo->siteDisk->free ?? 0);
		$siteDiskFreePct = $siteDiskTotal > 0 ? (100.0 * $siteDiskFree / $siteDiskTotal) : null;

		// DB disk (may differ)
		$dbDiskTotal = floatval($serverInfo->dbDisk->total ?? 0);
		$dbDiskFree  = floatval($serverInfo->dbDisk->free ?? 0);
		$dbDiskFreePct = $dbDiskTotal > 0 ? (100.0 * $dbDiskFree / $dbDiskTotal) : null;

		// CPU
		$ioWait = floatval(trim($serverInfo->cpuUsage?->iowait ?? 0));

		$detail = [
			'ram_used_pct'        => round($ramUsedPct, 2),
			'site_disk_free_pct'  => $siteDiskFreePct !== null ? round($siteDiskFreePct, 2) : null,
			'db_disk_free_pct'    => $dbDiskFreePct !== null ? round($dbDiskFreePct, 2) : null,
			'cpu_iowait_pct'      => round($ioWait, 2),
		];

		// Error threshold: RAM ≥ 85 %
		if ($memTotal > 0 && $ramUsedPct >= 85.0)
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		// Warning thresholds
		$isWarning = ($memTotal > 0 && $ramUsedPct >= 70.0)
			|| ($siteDiskFreePct !== null && $siteDiskFreePct <= 10.0)
			|| ($dbDiskFreePct !== null && $dbDiskFreePct <= 10.0)
			|| $ioWait >= 5.0;

		if ($isWarning)
		{
			return $this->area(HealthStatus::Warning, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * Extension update / health status.
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | No pending updates, no missing keys, no missing update sites |
	 * | warning | Updates available but no blocking issues |
	 * | error   | Missing download keys OR missing update sites |
	 * | unknown | No extension data collected yet |
	 *
	 * @since  1.6.2
	 */
	private function getExtensionsStatus(Site $site, Registry $config): array
	{
		$lastAttempt = $config->get('extensions.lastAttempt');

		if (empty($lastAttempt))
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'no_data']);
		}

		$quickInfo = $site->getExtensionsQuickInfo();

		$detail = [
			'updates_count'       => (int) $quickInfo->update,
			'missing_keys_count'  => (int) $quickInfo->key,
			'missing_sites_count' => (int) $quickInfo->site,
		];

		if ($quickInfo->key > 0 || $quickInfo->site > 0)
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		if ($quickInfo->update > 0)
		{
			return $this->area(HealthStatus::Warning, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * Backup health status.
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | Latest backup meta is ok/complete/remote AND not too old |
	 * | error   | No backup record, bad meta, or backup is too old |
	 * | unknown | Akeeba Backup Pro not linked to this site |
	 *
	 * @since  1.6.2
	 */
	private function getBackupStatus(Site $site, Registry $config): array
	{
		$hasPro = $config->get('akeebabackup.info.api', 0) > 1;

		if (!$hasPro || !$site->hasAkeebaBackup(true))
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'not_linked']);
		}

		$backupRecord = $config->get('akeebabackup.latest');
		$meta         = $backupRecord?->meta ?? null;
		$tooOld       = $this->isTooOldBackup($backupRecord, $config);

		$detail = [
			'meta'     => $meta,
			'too_old'  => $tooOld,
			'max_age'  => (int) $config->get('config.backup.max_age', 168),
			'backupstart' => $backupRecord?->backupstart ?? null,
		];

		if (empty($meta) || !in_array($meta, ['ok', 'complete', 'remote'], true) || $tooOld)
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * File scanner health status.
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | Latest scan completed successfully with 0 suspicious files |
	 * | error   | Latest scan failed OR suspicious files > 0 |
	 * | unknown | Admin Tools Professional not installed/linked |
	 *
	 * The scan data is retrieved from the local Symfony cache (populated by the file-scanner
	 * background task). If the cache is cold a fresh HTTP call to Admin Tools is made —
	 * consistent with what the Site Information page does.
	 *
	 * @since  1.6.2
	 */
	private function getFileScannerStatus(Site $site): array
	{
		if (!$site->hasAdminToolsPro())
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'not_installed']);
		}

		try
		{
			$scansData = $site->adminToolsGetScans(cache: true, from: 0, limit: 1);
		}
		catch (\Throwable)
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'api_error']);
		}

		$items = $scansData?->items ?? [];

		if (empty($items))
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'no_scans']);
		}

		$latest    = $items[0];
		$scanStatus = $latest?->status ?? null;         // 'complete' | 'run' | 'fail'
		$suspicious = (int) ($latest?->files_suspicious ?? 0);

		$detail = [
			'scan_status' => $scanStatus,
			'suspicious'  => $suspicious,
			'modified'    => (int) ($latest?->files_modified ?? 0),
			'total_files' => (int) ($latest?->totalfiles ?? 0),
			'scan_date'   => $latest?->scanstart ?? null,
		];

		if ($scanStatus !== 'complete' || $suspicious > 0)
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

	/**
	 * Core-file checksums status (Joomla only).
	 *
	 * | Status  | Condition |
	 * |---------|-----------|
	 * | ok      | Last check ran and all checksums matched |
	 * | warning | Last check found modified core files |
	 * | unknown | Not a Joomla site, or the check has never run |
	 *
	 * @since  1.6.2
	 */
	private function getCoreChecksumsStatus(Registry $config, CMSType $cmsType): array
	{
		if ($cmsType !== CMSType::JOOMLA)
		{
			return $this->area(HealthStatus::Unknown, [
				'reason' => 'wordpress_not_applicable',
			]);
		}

		$lastCheck     = $config->get('core.coreChecksums.lastCheck');
		$lastStatus    = $config->get('core.coreChecksums.lastStatus');
		$modifiedCount = (int) $config->get('core.coreChecksums.modifiedCount', 0);

		if ($lastCheck === null)
		{
			return $this->area(HealthStatus::Unknown, [
				'reason'    => 'never_run',
				'last_check' => null,
			]);
		}

		$detail = [
			'last_check'     => (int) $lastCheck,
			'last_status'    => $lastStatus !== null ? (bool) $lastStatus : null,
			'modified_count' => $modifiedCount,
		];

		if ($lastStatus === null || (bool) $lastStatus === true)
		{
			return $this->area(HealthStatus::Ok, $detail);
		}

		return $this->area(HealthStatus::Warning, $detail);
	}
}
