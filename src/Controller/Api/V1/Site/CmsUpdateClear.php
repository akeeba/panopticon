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
use Akeeba\Panopticon\Model\Task as TaskModel;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/cmsupdate/clear — clear a failed CMS update task.
 *
 * Mirrors the legacy `Controller\Sites::clearUpdateScheduleError()`.
 *
 * @since  1.4.0
 */
class CmsUpdateClear extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesCmsUpdate);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$user = $this->container->userManager->getUser();

		$cmsType = $site->cmsType();

		$taskType = match ($cmsType)
		{
			CMSType::JOOMLA    => 'joomlaupdate',
			CMSType::WORDPRESS => 'wordpressupdate',
			default            => null,
		};

		if ($taskType === null)
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
		}
		catch (\RuntimeException)
		{
			$this->sendJsonError(404, 'No CMS update task found for this site.', 'task.not_scheduled');
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to clear CMS update error: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.cmsupdate.clear',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value]
		);

		$this->sendJsonResponse(null, 200, 'CMS update error cleared successfully.');
	}
}
