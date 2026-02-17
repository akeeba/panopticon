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
 * API handler: PUT /v1/task
 *
 * Creates a new task from the provided JSON body fields.
 *
 * @since  1.4.0
 */
class Add extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$body = $this->getJsonBody();

		if (empty($body['type']))
		{
			$this->sendJsonError(400, 'Missing required field: type');
		}

		if (!isset($body['cron_expression']) || $body['cron_expression'] === '')
		{
			$this->sendJsonError(400, 'Missing required field: cron_expression');
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		$data = [
			'site_id'         => $body['site_id'] ?? null,
			'type'            => $body['type'],
			'cron_expression' => $body['cron_expression'],
			'enabled'         => (int) ($body['enabled'] ?? 1),
			'params'          => new Registry($body['params'] ?? []),
			'storage'         => '{}',
		];

		try
		{
			$model->save($data);
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Failed to create task: ' . $e->getMessage());
		}

		$this->sendJsonResponse(
			[
				'id'              => $model->id,
				'site_id'         => $model->site_id,
				'type'            => $model->type,
				'params'          => $model->getParams()->toArray(),
				'cron_expression' => (string) $model->cron_expression,
				'enabled'         => (bool) $model->enabled,
				'next_execution'  => $model->next_execution,
			],
			201,
			'Task created successfully.'
		);
	}
}
