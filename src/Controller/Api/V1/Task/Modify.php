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
use Awf\Registry\Registry;

/**
 * API handler: POST /v1/task/:id
 *
 * Modifies an existing task. Updatable fields: params, cron_expression, enabled.
 *
 * @since  1.4.0
 */
class Modify extends AbstractApiHandler
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

		$body       = $this->getJsonBody();
		$updateData = [];

		if (array_key_exists('params', $body))
		{
			$updateData['params'] = new Registry($body['params']);
		}

		if (array_key_exists('cron_expression', $body))
		{
			$updateData['cron_expression'] = $body['cron_expression'];
		}

		if (array_key_exists('enabled', $body))
		{
			$updateData['enabled'] = (int) $body['enabled'];
		}

		if (empty($updateData))
		{
			$this->sendJsonError(400, 'No updatable fields provided. Supported fields: params, cron_expression, enabled.');
		}

		try
		{
			$model->save($updateData);
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Failed to update task: ' . $e->getMessage());
		}

		$this->sendJsonResponse([
			'id'              => $model->id,
			'site_id'         => $model->site_id,
			'type'            => $model->type,
			'params'          => $model->getParams()->toArray(),
			'cron_expression' => (string) $model->cron_expression,
			'enabled'         => (bool) $model->enabled,
			'last_exit_code'  => $model->last_exit_code,
			'next_execution'  => $model->next_execution,
		]);
	}
}
