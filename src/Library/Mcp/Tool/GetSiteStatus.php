<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\HealthStatus;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Library\SoftwareVersions\JoomlaVersion;
use Akeeba\Panopticon\Library\SoftwareVersions\PhpVersion;
use Akeeba\Panopticon\Library\SoftwareVersions\WordPressVersion;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\AkeebaBackupTooOldTrait;
use Awf\Registry\Registry;

/**
 * MCP tool: per-site health summary.
 *
 * Mirrors `GET /api/v1/site/:id/status` (same `panopticon.read` ACL), reporting a four-value health status
 * (ok / warning / error / unknown) for each monitored area of the site.
 *
 * @since  2.2.0
 */
class GetSiteStatus extends AbstractTool
{
	use AkeebaBackupTooOldTrait;

	public function getName(): string
	{
		return 'get_site_status';
	}

	public function getDescription(): string
	{
		return 'Get a health summary for a single site: CMS update status, PHP version health, extension updates, '
			. 'and backup status. Each area reports ok, warning, error or unknown with supporting details.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesRead;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'site_id' => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site.',
				],
				'id'      => [
					'type'        => 'integer',
					'description' => 'Alias for site_id. The numeric ID of the site.',
				],
			],
			'required'   => ['site_id'],
		];
	}

	public function __invoke(int $site_id = 0, int $id = 0): array
	{
		$site    = $this->getSiteWithPermission($site_id ?: $id, 'read');
		$config  = $site->getConfig();
		$cmsType = $site->cmsType();

		return [
			'id'      => (int) $site->getId(),
			'name'    => $site->name,
			'enabled' => (bool) $site->enabled,
			'areas'   => [
				'cms_update' => $this->getCmsUpdateStatus($config, $cmsType),
				'php'        => $this->getPhpStatus($config),
				'extensions' => $this->getExtensionsStatus($site, $config),
				'backup'     => $this->getBackupStatus($site, $config),
			],
		];
	}

	private function area(HealthStatus $status, array $detail = []): array
	{
		return ['status' => $status->value, 'detail' => $detail];
	}

	private function getCmsUpdateStatus(Registry $config, CMSType $cmsType): array
	{
		$currentVersion = $config->get('core.current.version');
		$latestVersion  = $config->get('core.latest.version');
		$canUpgrade     = (bool) $config->get('core.canUpgrade', false);

		if (empty($currentVersion))
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'no_data']);
		}

		$detail = [
			'current_version' => $currentVersion,
			'latest_version'  => $latestVersion,
			'can_upgrade'     => $canUpgrade,
		];

		if ($cmsType === CMSType::JOOMLA)
		{
			$helper              = new JoomlaVersion($this->container);
			$extensionAvailable  = (bool) $config->get('core.extensionAvailable', true);
			$updateSiteAvailable = (bool) $config->get('core.updateSiteAvailable', true);

			if (!$extensionAvailable || !$updateSiteAvailable
				|| $helper->isEOLMajor($currentVersion) || $helper->isEOLBranch($currentVersion))
			{
				return $this->area(HealthStatus::Error, $detail);
			}
		}
		elseif ($cmsType === CMSType::WORDPRESS)
		{
			$helper = new WordPressVersion($this->container);

			if ($helper->isEOL($currentVersion))
			{
				return $this->area(HealthStatus::Error, $detail);
			}
		}

		return $this->area($canUpgrade ? HealthStatus::Warning : HealthStatus::Ok, $detail);
	}

	private function getPhpStatus(Registry $config): array
	{
		$php = $config->get('core.php', '0.0.0') ?: '0.0.0';

		if ($php === '0.0.0')
		{
			return $this->area(HealthStatus::Unknown, ['reason' => 'no_data']);
		}

		$phpVersion     = new PhpVersion($this->container);
		$versionInfo    = $phpVersion->getVersionInformation($php);
		$latestInBranch = $versionInfo?->latest;
		$isLatestPatch  = version_compare($php, $latestInBranch ?? '0.0.0', 'ge');

		$detail = [
			'version'          => $php,
			'eol'              => $versionInfo?->eol ?? true,
			'security_only'    => $versionInfo?->security ?? false,
			'latest_in_branch' => $latestInBranch,
			'is_latest_patch'  => $isLatestPatch,
		];

		if ($versionInfo?->unknown ?? false)
		{
			return $this->area(HealthStatus::Unknown, array_merge($detail, ['reason' => 'unknown_version']));
		}

		if ($phpVersion->isEOL($php))
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		if ($phpVersion->isSecurity($php) || !$isLatestPatch)
		{
			return $this->area(HealthStatus::Warning, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}

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
			'meta'        => $meta,
			'too_old'     => $tooOld,
			'backupstart' => $backupRecord?->backupstart ?? null,
		];

		if (empty($meta) || !in_array($meta, ['ok', 'complete', 'remote'], true) || $tooOld)
		{
			return $this->area(HealthStatus::Error, $detail);
		}

		return $this->area(HealthStatus::Ok, $detail);
	}
}
