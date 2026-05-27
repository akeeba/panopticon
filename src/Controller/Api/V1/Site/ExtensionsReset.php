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
use Akeeba\Panopticon\Library\Queue\QueueInterface;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Task\Trait\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueuePluginUpdateTrait;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/extensions/reset — purge the extensions queue and reschedule.
 *
 * Mirrors `Controller\Sites::resetExtensionUpdate()`. Reuses the same trait helpers
 * (`scheduleExtensionsUpdateForSite`, `schedulePluginsUpdateForSite`) the legacy controller calls.
 *
 * Request body (optional):
 *   { "resetqueue": true }   // also empty the per-site update queue
 *
 * @since  1.4.0
 */
class ExtensionsReset extends AbstractApiHandler
{
	use EnqueueExtensionUpdateTrait;
	use EnqueuePluginUpdateTrait;

	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesExtensions);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$user = $this->container->userManager->getUser();

		$body       = $this->getJsonBody();
		$resetQueue = (bool) ($body['resetqueue'] ?? false);

		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			$this->sendJsonError(422, 'Unsupported CMS type.', 'site.wrong_cms');
		}

		try
		{
			if ($resetQueue)
			{
				$queuePattern = match ($cmsType)
				{
					CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
					CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
				};

				$queueKey = sprintf($queuePattern, $site->getId());
				/** @var QueueInterface $queue */
				$queue = $this->container->queueFactory->makeQueue($queueKey);
				$queue->clear();
			}

			if ($cmsType === CMSType::JOOMLA)
			{
				$this->scheduleExtensionsUpdateForSite($site, $this->container);
			}
			else
			{
				$this->schedulePluginsUpdateForSite($site, $this->container);
			}
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to reset extensions update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extensions.reset',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value, 'resetQueue' => $resetQueue]
		);

		$this->sendJsonResponse(null, 200, 'Extensions update reset successfully.');
	}
}
