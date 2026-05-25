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
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\AuditLog;

/**
 * API handler for POST /v1/site/:id/cmsupdate/cancel — cancel (unpublish) a scheduled CMS update.
 *
 * Mirrors the legacy `Controller\Sites::unscheduleJoomlaUpdate()` /
 * `unscheduleWordPressUpdate()` flow: lookup the task via `Model\Site::getJoomlaUpdateTask()` /
 * `getWordPressUpdateTask()` (both already in `Model\Site`), validate state, unpublish.
 *
 * @since  1.4.0
 */
class CmsUpdateCancel extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$user = $this->container->userManager->getUser();

		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			$this->sendJsonError(422, 'Unsupported CMS type.', 'site.wrong_cms');
		}

		$task = $cmsType === CMSType::JOOMLA
			? $site->getJoomlaUpdateTask()
			: $site->getWordPressUpdateTask();

		if ($task === null)
		{
			$this->sendJsonError(404, 'No scheduled CMS update task found for this site.', 'task.not_scheduled');
		}

		if (in_array(
			(int) $task->last_exit_code,
			[Status::WILL_RESUME->value, Status::RUNNING->value],
			true
		))
		{
			$this->sendJsonError(422, 'The CMS update is currently running and cannot be cancelled.', 'task.running');
		}

		try
		{
			$task->last_exit_code = Status::OK->value;
			$task->unpublish();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to cancel CMS update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.cmsupdate.cancel',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value]
		);

		$this->sendJsonResponse(null, 200, 'CMS update cancelled successfully.');
	}
}
