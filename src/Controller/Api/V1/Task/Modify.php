<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Task as TaskModel;
use RuntimeException;

/**
 * API handler: POST /v1/task/:id
 *
 * Modify an existing task. Updatable fields: params, cron_expression, enabled, type.
 *
 * ACL: super-user always; OR `panopticon.admin` on the task's site (for site-bound tasks).
 *
 * @since  1.4.0
 */
class Modify extends AbstractApiHandler
{
	public function handle(): void
	{
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
					'You do not have permission to modify this task.',
					'auth.forbidden'
				);
			}
		}

		$body       = $this->getJsonBody();
		$updateData = [];

		if (array_key_exists('params', $body))
		{
			$updateData['params'] = $body['params'];
		}

		if (array_key_exists('cron_expression', $body))
		{
			$updateData['cron_expression'] = (string) $body['cron_expression'];
		}

		if (array_key_exists('enabled', $body))
		{
			$updateData['enabled'] = (int) (bool) $body['enabled'];
		}

		if (array_key_exists('type', $body))
		{
			$updateData['type'] = (string) $body['type'];
		}

		if (empty($updateData))
		{
			$this->sendJsonError(
				400,
				'No updatable fields provided. Supported fields: type, params, cron_expression, enabled.',
				'validation.bad_request'
			);
		}

		try
		{
			$model->validateAndSave($updateData);
		}
		catch (RuntimeException $e)
		{
			$code = $e->getCode();

			if ($code === 422)
			{
				$errorCode = str_contains($e->getMessage(), 'cron')
					? 'task.invalid_cron'
					: 'task.unknown_type';

				$this->sendJsonError(422, $e->getMessage(), $errorCode);
			}

			if ($code === 400)
			{
				$this->sendJsonError(400, $e->getMessage(), 'validation.bad_request');
			}

			$this->sendJsonError(500, 'Failed to update task: ' . $e->getMessage());
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to update task: ' . $e->getMessage());
		}

		AuditLog::record(
			'task.update',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'task',
			(int) $model->id,
			['fields' => array_keys($updateData)]
		);

		$this->sendJsonResponse($model->toApiArray());
	}
}
