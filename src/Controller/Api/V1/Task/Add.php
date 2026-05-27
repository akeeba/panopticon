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
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: PUT /v1/task
 *
 * Create a new task. Body fields: site_id (nullable for system tasks), type, params,
 * cron_expression, enabled.
 *
 * ACL: super-user always; OR `panopticon.admin` on the target `site_id` for site tasks.
 * System tasks (`site_id` null/0) require super.
 *
 * @since  1.4.0
 */
class Add extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::TasksWrite);
		$body = $this->getJsonBody();

		$siteId = $body['site_id'] ?? null;

		if ($siteId !== null)
		{
			$siteId = (int) $siteId;

			if ($siteId <= 0)
			{
				$siteId = null;
			}
		}

		$user    = $this->container->userManager->getUser();
		$isSuper = (bool) $user->getPrivilege('panopticon.super');

		if (!$isSuper)
		{
			if ($siteId === null)
			{
				$this->sendJsonError(
					403,
					'Creating system tasks requires super-user privileges.',
					'auth.forbidden'
				);
			}

			if (!$user->authorise('panopticon.admin', $siteId))
			{
				$this->sendJsonError(
					403,
					'You do not have admin permission on the target site.',
					'auth.forbidden'
				);
			}
		}

		if (empty($body['type']))
		{
			$this->sendJsonError(400, 'Missing required field: type', 'validation.bad_request');
		}

		if (!isset($body['cron_expression']) || $body['cron_expression'] === '')
		{
			$this->sendJsonError(400, 'Missing required field: cron_expression', 'validation.bad_request');
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		$data = [
			'site_id'         => $siteId,
			'type'            => (string) $body['type'],
			'cron_expression' => (string) $body['cron_expression'],
			'enabled'         => (int) (bool) ($body['enabled'] ?? 1),
			'params'          => $body['params'] ?? [],
			'storage'         => '{}',
		];

		try
		{
			$model->validateAndSave($data);
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

			$this->sendJsonError(500, 'Failed to create task: ' . $e->getMessage());
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to create task: ' . $e->getMessage());
		}

		AuditLog::record(
			'task.create',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'task',
			(int) $model->id,
			[
				'type'    => $model->type,
				'site_id' => $siteId,
			]
		);

		$this->sendJsonResponse(
			$model->toApiArray(),
			201,
			'Task created successfully.'
		);
	}
}
