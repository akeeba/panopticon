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
use Akeeba\Panopticon\Library\Mcp\AbstractTool;

/**
 * MCP tool: list a site's extensions (Joomla) or plugins/themes (WordPress).
 *
 * Mirrors `GET /api/v1/site/:id/extensions` (same `panopticon.read` ACL).
 *
 * Stored download keys present in extension records are stripped before returning, to avoid leaking secrets into the
 * AI agent's context. Everything else needed to understand update state is preserved.
 *
 * @since  2.2.0
 */
class ListSiteExtensions extends AbstractTool
{
	public function getName(): string
	{
		return 'list_site_extensions';
	}

	public function getDescription(): string
	{
		return 'List the extensions (Joomla) or plugins and themes (WordPress) installed on a site, including their '
			. 'current and latest versions and whether an update is available.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesExtensions;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'site_id'      => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site in Panopticon.',
				],
				'id'           => [
					'type'        => 'integer',
					'description' => 'Alias for site_id. The numeric ID of the site in Panopticon.',
				],
				'updates_only' => [
					'type'        => 'boolean',
					'description' => 'When true, only return extensions that have an update available (default false).',
				],
			],
			'required'   => ['site_id'],
		];
	}

	public function __invoke(int $site_id = 0, int $id = 0, bool $updates_only = false): array
	{
		$resolvedId  = $site_id ?: $id;
		$site        = $this->getSiteWithPermission($resolvedId, 'read');
		$extensions  = $site->getExtensionsList();
		$isWordPress = $site->cmsType() === CMSType::WORDPRESS;

		$result      = [];
		$cntUpdates  = 0;
		$cntKeys     = 0;
		$cntSites    = 0;

		foreach ($extensions as $extId => $extension)
		{
			$current   = $extension?->version?->current ?? null;
			$latest    = $extension?->version?->new ?? null;
			$hasUpdate = !empty($latest) && !empty($current)
				&& ($current != $latest)
				&& version_compare($current, $latest, 'lt');

			// Tally summary counts from every extension, regardless of updates_only filter.
			if ($hasUpdate)
			{
				$cntUpdates++;
			}

			if (!($extension->hasUpdateSites ?? false))
			{
				$cntSites++;
			}

			if (($extension->downloadkey?->supported ?? false) && !($extension->downloadkey?->valid ?? false))
			{
				$cntKeys++;
			}

			if ($updates_only && !$hasUpdate)
			{
				continue;
			}

			// WordPress plugins/themes use a composite string key (`plg_folder_element` or `tpl_element`)
			// matching what PluginsUpdate expects. Joomla extensions use their numeric extension_id.
			if ($isWordPress)
			{
				$extId = ($extension->type === 'plugin' ? 'plg_' : 'tpl_')
					. trim(implode('_', [(string) ($extension->folder ?? ''), (string) ($extension->element ?? '')]), '_');
			}
			else
			{
				$extId = (int) $extId;
			}

			$result[] = [
				'id'               => $extId,
				'name'             => $extension->name ?? null,
				'type'             => $extension->type ?? null,
				'author'           => $extension->author ?? null,
				'current_version'  => $current,
				'latest_version'   => $latest,
				'update_available' => $hasUpdate,
			];
		}

		return [
			'extensions' => $result,
			'summary'    => [
				'updates'       => $cntUpdates,
				'missing_keys'  => $cntKeys,
				'missing_sites' => $cntSites,
			],
		];
	}
}
