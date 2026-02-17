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
 * API handler: GET /v1/task/:id
 *
 * Returns full details for a single task, including params and storage.
 *
 * @since  1.4.0
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$id = $this->input->getInt('id', 0);

		if ($id <= 0)
		{
			$this->sendJsonError(400, 'Missing or invalid required parameter: id');
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(404, 'Task not found.');
		}

		$this->sendJsonResponse([
			'id'              => $model->id,
			'site_id'         => $model->site_id,
			'type'            => $model->type,
			'params'          => $model->getParams()->toArray(),
			'storage'         => $model->getStorage()->toArray(),
			'cron_expression' => (string) $model->cron_expression,
			'enabled'         => (bool) $model->enabled,
			'last_exit_code'  => $model->last_exit_code,
			'last_execution'  => $model->last_execution,
			'last_run_end'    => $model->last_run_end,
			'next_execution'  => $model->next_execution,
			'times_executed'  => $model->times_executed,
			'times_failed'    => $model->times_failed,
			'locked'          => $model->locked,
			'priority'        => $model->priority,
		]);
	}
}
