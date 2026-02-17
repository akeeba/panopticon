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
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Model\Task as TaskModel;

/**
 * API handler for POST /v1/site/:id/extensions/reset — purge the extensions update queue and delete the task.
 *
 * @since  1.4.0
 */
class ExtensionsReset extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');

		try
		{
			// Purge the extensions update queue
			$queuePattern = match ($site->cmsType())
			{
				CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
				CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
				default            => null,
			};

			if ($queuePattern !== null)
			{
				$queueKey = sprintf($queuePattern, $site->getId());
				$queue    = $this->container->queueFactory->makeQueue($queueKey);
				$queue->clear();
			}

			// Delete the extensions update task
			$taskType = match ($site->cmsType())
			{
				CMSType::JOOMLA    => 'extensionsupdate',
				CMSType::WORDPRESS => 'pluginsupdate',
				default            => null,
			};

			if ($taskType !== null)
			{
				/** @var TaskModel $task */
				$task = $this->container->mvcFactory->makeTempModel('Task');

				try
				{
					$task->findOrFail([
						'site_id' => $site->getId(),
						'type'    => $taskType,
					]);

					$task->delete();
				}
				catch (\RuntimeException)
				{
					// No task found — that is fine, nothing to delete
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to reset extensions update: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Extensions update queue and task reset successfully.');
	}
}
