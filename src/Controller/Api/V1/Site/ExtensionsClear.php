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
use Akeeba\Panopticon\Model\Task as TaskModel;
use Akeeba\Panopticon\Task\Trait\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueuePluginUpdateTrait;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/extensions/clear — clear a failed extensions update task.
 *
 * Mirrors `Controller\Sites::clearExtensionUpdatesScheduleError()`: deletes the failed task,
 * then re-schedules if the per-site update queue still has pending items. Reuses the same
 * `scheduleExtensionsUpdateForSite()` / `schedulePluginsUpdateForSite()` traits the legacy
 * controller uses.
 *
 * @since  1.4.0
 */
class ExtensionsClear extends AbstractApiHandler
{
	use EnqueueExtensionUpdateTrait;
	use EnqueuePluginUpdateTrait;

	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesExtensions);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$user = $this->container->userManager->getUser();

		$cmsType = $site->cmsType();

		$taskType = match ($cmsType)
		{
			CMSType::JOOMLA    => 'extensionsupdate',
			CMSType::WORDPRESS => 'pluginsupdate',
			default            => null,
		};

		$queuePattern = match ($cmsType)
		{
			CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
			CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
			default            => null,
		};

		if ($taskType === null || $queuePattern === null)
		{
			$this->sendJsonError(422, 'Unsupported CMS type.', 'site.wrong_cms');
		}

		try
		{
			/** @var TaskModel $task */
			$task = $this->container->mvcFactory->makeTempModel('Task');

			$task->findOrFail([
				'site_id' => $site->getId(),
				'type'    => $taskType,
			]);

			$task->delete();

			// If there are still pending items, reschedule (matches legacy controller).
			$queueKey = sprintf($queuePattern, $site->getId());
			/** @var QueueInterface $queue */
			$queue = $this->container->queueFactory->makeQueue($queueKey);

			if ($queue->count())
			{
				if ($cmsType === CMSType::JOOMLA)
				{
					$this->scheduleExtensionsUpdateForSite($site, $this->container);
				}
				else
				{
					$this->schedulePluginsUpdateForSite($site, $this->container);
				}
			}
		}
		catch (\RuntimeException)
		{
			$this->sendJsonError(404, 'No extensions update task found for this site.', 'task.not_scheduled');
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to clear extensions update error: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extensions.clear',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value]
		);

		$this->sendJsonResponse(null, 200, 'Extensions update error cleared successfully.');
	}
}
