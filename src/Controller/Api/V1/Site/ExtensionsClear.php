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
 * API handler for POST /v1/site/:id/extensions/clear — clear a failed extensions update task.
 *
 * @since  1.4.0
 */
class ExtensionsClear extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');

		$taskType = match ($site->cmsType())
		{
			CMSType::JOOMLA    => 'extensionsupdate',
			CMSType::WORDPRESS => 'pluginsupdate',
			default            => null,
		};

		if ($taskType === null)
		{
			$this->sendJsonError(400, 'Unsupported CMS type.');
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

			// If there are still items in the queue, the task needs to be rescheduled.
			// We leave that to the caller or the next scheduled run.
		}
		catch (\RuntimeException)
		{
			$this->sendJsonError(404, 'No extensions update task found for this site.');
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to clear extensions update error: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Extensions update error cleared successfully.');
	}
}
