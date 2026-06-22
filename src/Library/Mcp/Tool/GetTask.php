<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Model\Task as TaskModel;

/**
 * MCP tool: get a single scheduled task by ID.
 *
 * Mirrors `GET /api/v1/task/:id`: Super Users always; otherwise `panopticon.admin` on the task's site.
 *
 * @since  2.2.0
 */
class GetTask extends AbstractTool
{
	public function getName(): string
	{
		return 'get_task';
	}

	public function getDescription(): string
	{
		return 'Get full details for a single scheduled task by its numeric ID, including its type, schedule, '
			. 'last run time and last exit status.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::TasksRead;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the task.',
				],
			],
			'required'   => ['id'],
		];
	}

	public function __invoke(int $id): array
	{
		if ($id <= 0)
		{
			throw new \RuntimeException('Missing or invalid required parameter: id');
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		try
		{
			$model->findOrFail($id);
		}
		catch (\Throwable)
		{
			throw new \RuntimeException(sprintf('Task %d was not found.', $id));
		}

		$user = $this->getUser();

		if (!$user->getPrivilege('panopticon.super'))
		{
			$siteId = $model->site_id === null ? 0 : (int) $model->site_id;

			if ($siteId <= 0 || !$user->authorise('panopticon.admin', $siteId))
			{
				throw new \RuntimeException('You do not have permission to view this task.');
			}
		}

		return $model->toApiArray();
	}
}
