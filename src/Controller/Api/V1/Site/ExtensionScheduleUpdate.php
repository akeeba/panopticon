<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Task\Trait\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueuePluginUpdateTrait;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/extensions/scheduleupdate/:extId — enqueue an extension for update.
 *
 * DRY: uses {@see EnqueueExtensionUpdateTrait::enqueueExtensionUpdate()} +
 * {@see EnqueueExtensionUpdateTrait::scheduleExtensionsUpdateForSite()} (Joomla) and
 * {@see EnqueuePluginUpdateTrait::enqueuePluginUpdate()} +
 * {@see EnqueuePluginUpdateTrait::schedulePluginsUpdateForSite()} (WordPress) — the same helpers
 * the legacy `Controller\Sites::scheduleExtensionUpdate()` / `schedulePluginUpdate()` use.
 *
 * @since  1.4.0
 */
class ExtensionScheduleUpdate extends AbstractApiHandler
{
	use EnqueueExtensionUpdateTrait;
	use EnqueuePluginUpdateTrait;

	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesExtensions);
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'run');
		$user  = $this->container->userManager->getUser();

		if ($extId <= 0)
		{
			$this->sendJsonError(400, 'Invalid extension ID.', 'validation.bad_request');
		}

		// Verify the extension is known on this site
		$extensions = (array) $site->getConfig()->get('extensions.list');

		if (!array_key_exists($extId, $extensions))
		{
			$this->sendJsonError(404, 'Extension not found on this site.', 'extension.not_found');
		}

		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			$this->sendJsonError(422, 'Unsupported CMS type for extension updates.', 'site.wrong_cms');
		}

		try
		{
			if ($cmsType === CMSType::JOOMLA)
			{
				$enqueued = $this->enqueueExtensionUpdate($site, $extId, user: $user);
			}
			else
			{
				// WordPress plugins/themes are indexed by integer in extensions.list but PluginsUpdate expects
				// the composite `plg_folder_element` / `tpl_element` key. Derive it from the extension object.
				$ext        = $extensions[$extId];
				$softwareId = (($ext->type === 'plugin') ? 'plg_' : 'tpl_')
					. trim(implode('_', [(string) ($ext->folder ?? ''), (string) ($ext->element ?? '')]), '_');

				$enqueued = $this->enqueuePluginUpdate($site, $softwareId, user: $user);
			}

			if (!$enqueued)
			{
				$this->sendJsonError(
					409,
					'Extension is already queued for update.',
					'task.already_scheduled'
				);
			}

			if ($cmsType === CMSType::JOOMLA)
			{
				$this->scheduleExtensionsUpdateForSite($site, $this->container, runNow: true);
			}
			else
			{
				$this->schedulePluginsUpdateForSite($site, $this->container, runNow: true);
			}
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to schedule extension update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extension.scheduleupdate',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value, 'extensionId' => $extId]
		);

		$this->sendJsonResponse(null, 202, 'Extension update scheduled successfully.');
	}
}
