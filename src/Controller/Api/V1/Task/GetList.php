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
 * @since  1.4.0
 */
class GetList extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		// Apply filters from query parameters
		$siteId  = $this->input->getString('site_id', '');
		$type    = $this->input->getString('type', '');
		$enabled = $this->input->getString('enabled', '');

		if ($siteId !== '')
		{
			$model->setState('site_id', $siteId);
		}

		if ($type !== '')
		{
			$model->setState('type', $type);
		}

		if ($enabled !== '')
		{
			$model->setState('enabled', $enabled);
		}

		// Pagination
		$limit  = $this->input->getInt('limit', 50);
		$offset = $this->input->getInt('offset', 0);

		$model->setState('limit', $limit);
		$model->setState('limitstart', $offset);

		$items = $model->get();
		$tasks = [];

		foreach ($items as $task)
		{
			/** @var TaskModel $task */
			$tasks[] = [
				'id'              => $task->id,
				'site_id'         => $task->site_id,
				'type'            => $task->type,
				'enabled'         => (bool) $task->enabled,
				'last_exit_code'  => $task->last_exit_code,
				'next_execution'  => $task->next_execution,
				'cron_expression' => (string) $task->cron_expression,
			];
		}

		$this->sendJsonResponse(
			$tasks,
			pagination: [
				'offset' => $offset,
				'limit'  => $limit,
				'count'  => count($tasks),
			]
		);
	}
}
