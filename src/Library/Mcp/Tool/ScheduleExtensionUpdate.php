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
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Task\Trait\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueuePluginUpdateTrait;

/**
 * MCP tool: schedule an update for a single extension/plugin on a site.
 *
 * Mirrors `POST /api/v1/site/:id/extensions/scheduleupdate/:extId` (scope `sites:extensions`, per-site
 * `panopticon.run`).
 *
 * @since  2.2.0
 */
class ScheduleExtensionUpdate extends AbstractTool
{
	use EnqueueExtensionUpdateTrait;
	use EnqueuePluginUpdateTrait;

	public function getName(): string
	{
		return 'schedule_extension_update';
	}

	public function getDescription(): string
	{
		return 'Schedule an update for a single extension (Joomla) or plugin (WordPress) on a site, identified by its '
			. 'extension ID. The update runs in the background on the next task run.';
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
					'description' => 'The numeric ID of the site.',
				],
				'id'           => [
					'type'        => 'integer',
					'description' => 'Alias for site_id. The numeric ID of the site.',
				],
				'extension_id' => [
					'type'        => 'string',
					'description' => 'The ID of the extension/plugin to update, exactly as returned by list_site_extensions '
						. '(a numeric string for Joomla extensions; a "plg_…" or "tpl_…" string for WordPress plugins/themes).',
				],
			],
			'required'   => ['site_id', 'extension_id'],
		];
	}

	public function __invoke(int $site_id = 0, int $id = 0, string $extension_id = ''): array
	{
		$site = $this->getSiteWithPermission($site_id ?: $id, 'run');
		$user = $this->getUser();

		if (empty($extension_id))
		{
			throw new \RuntimeException('Invalid extension ID.');
		}

		$cmsType    = $site->cmsType();
		$extensions = (array) $site->getConfig()->get('extensions.list');

		if ($cmsType === CMSType::JOOMLA)
		{
			$numericId = (int) $extension_id;

			if ($numericId <= 0)
			{
				throw new \RuntimeException('Invalid extension ID.');
			}

			if (!array_key_exists($numericId, $extensions))
			{
				throw new \RuntimeException('Extension not found on this site.');
			}

			try
			{
				$enqueued = $this->enqueueExtensionUpdate($site, $numericId, user: $user);

				if (!$enqueued)
				{
					throw new \RuntimeException('Extension is already queued for update.');
				}

				$this->scheduleExtensionsUpdateForSite($site, $this->container, runNow: true);
			}
			catch (\RuntimeException $e)
			{
				throw $e;
			}
			catch (\Throwable $e)
			{
				throw new \RuntimeException('Failed to schedule extension update: ' . $e->getMessage());
			}
		}
		elseif ($cmsType === CMSType::WORDPRESS)
		{
			// Build the same composite key map that PluginsUpdate uses, so the queued ID resolves correctly.
			$extKeys         = array_map(
				fn($item) => (($item->type === 'plugin') ? 'plg_' : 'tpl_')
					. trim(implode('_', [(string) ($item->folder ?? ''), (string) ($item->element ?? '')]), '_'),
				$extensions
			);
			$extensionsByKey = array_combine($extKeys, $extensions);

			if (!array_key_exists($extension_id, $extensionsByKey))
			{
				throw new \RuntimeException('Extension not found on this site.');
			}

			try
			{
				$enqueued = $this->enqueuePluginUpdate($site, $extension_id, user: $user);

				if (!$enqueued)
				{
					throw new \RuntimeException('Extension is already queued for update.');
				}

				$this->schedulePluginsUpdateForSite($site, $this->container, runNow: true);
			}
			catch (\RuntimeException $e)
			{
				throw $e;
			}
			catch (\Throwable $e)
			{
				throw new \RuntimeException('Failed to schedule extension update: ' . $e->getMessage());
			}
		}
		else
		{
			throw new \RuntimeException('Unsupported CMS type for extension updates.');
		}

		AuditLog::record(
			'site.extension.scheduleupdate',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value, 'extensionId' => $extension_id]
		);

		return [
			'success' => true,
			'message' => 'Extension update scheduled successfully.',
		];
	}
}
