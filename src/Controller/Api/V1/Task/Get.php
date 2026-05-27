<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\Task as TaskModel;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: GET /v1/task/:id
 *
 * Returns full details for a single task.
 *
 * ACL: super-user always; OR `panopticon.admin` on the task's site (for site-bound tasks).
 *
 * @since  1.4.0
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::TasksRead);
		$id = $this->input->getInt('id', 0);

		if ($id <= 0)
		{
			$this->sendJsonError(400, 'Missing or invalid required parameter: id', 'validation.bad_request');
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception)
		{
			$this->sendJsonError(404, 'Task not found.', 'task.not_found');
		}

		$user    = $this->container->userManager->getUser();
		$isSuper = (bool) $user->getPrivilege('panopticon.super');

		if (!$isSuper)
		{
			$siteId = $model->site_id === null ? 0 : (int) $model->site_id;

			if ($siteId <= 0 || !$user->authorise('panopticon.admin', $siteId))
			{
				$this->sendJsonError(
					403,
					'You do not have permission to view this task.',
					'auth.forbidden'
				);
			}
		}

		$this->sendJsonResponse($model->toApiArray());
	}
}
