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

/**
 * API handler: GET /v1/tasks
 *
 * Returns a paginated list of tasks, optionally filtered by site_id, type, and enabled status.
 *
 * ACL: super-user (mirrors the legacy `view=tasks` ACL). When a non-super-user has
 * `panopticon.admin` on a specific site, they may filter by that `site_id` to retrieve the
 * tasks scoped to that site.
 *
 * @since  1.4.0
 */
class GetList extends AbstractApiHandler
{
	public function handle(): void
	{
		$user      = $this->container->userManager->getUser();
		$isSuper   = (bool) $user->getPrivilege('panopticon.super');
		$siteParam = $this->input->getString('site_id', '');
		$siteId    = ($siteParam === '' || $siteParam === null) ? null : (int) $siteParam;

		if (!$isSuper)
		{
			// Non-super users MUST scope the query to a single site they administer.
			if ($siteId === null || $siteId <= 0)
			{
				$this->sendJsonError(
					403,
					'Listing tasks requires super-user privileges; non-super users must filter by an administered site_id.',
					'auth.forbidden'
				);
			}

			if (!$user->authorise('panopticon.admin', $siteId))
			{
				$this->sendJsonError(
					403,
					'You do not have admin permission on this site.',
					'auth.forbidden'
				);
			}
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		if ($siteParam !== '')
		{
			$model->setState('site_id', $siteParam);
		}

		$type = $this->input->getString('type', '');

		if ($type !== '')
		{
			$model->setState('type', $type);
		}

		$enabled = $this->input->get('enabled', null);

		if ($enabled !== null && $enabled !== '')
		{
			$model->setState('enabled', (int) (bool) $enabled);
		}

		$limit  = max(0, min(200, $this->input->getInt('limit', 50)));
		$offset = max(0, $this->input->getInt('offset', 0));

		$model->setState('limitstart', $offset);
		$model->setState('limit', $limit);

		$items = $model->get(true);
		$total = $model->count();

		$tasks = [];

		/** @var TaskModel $task */
		foreach ($items as $task)
		{
			$tasks[] = $task->toApiArray();
		}

		$this->sendJsonResponse(
			$tasks,
			pagination: [
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			]
		);
	}
}
