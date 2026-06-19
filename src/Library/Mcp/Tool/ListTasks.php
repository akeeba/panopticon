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
 * MCP tool: list scheduled tasks.
 *
 * Mirrors `GET /api/v1/tasks`. Super Users can list all tasks; other users must pass a `site_id` they administer
 * (`panopticon.admin`) and only see that site's tasks — exactly as the API enforces.
 *
 * @since  2.2.0
 */
class ListTasks extends AbstractTool
{
	public function getName(): string
	{
		return 'list_tasks';
	}

	public function getDescription(): string
	{
		return 'List scheduled background tasks (such as updates, backups and scans). Super Users may list all '
			. 'tasks; other users must provide the ID of a site they administer to list that site\'s tasks.';
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
				'site_id' => [
					'type'        => 'integer',
					'description' => 'Restrict to tasks of this site. Required for non-Super-User accounts.',
				],
				'type'    => [
					'type'        => 'string',
					'description' => 'Restrict to tasks of this type (e.g. "joomlaupdate", "filescanner").',
				],
				'enabled' => [
					'type'        => 'boolean',
					'description' => 'Only return enabled (true) or disabled (false) tasks.',
				],
				'limit'   => [
					'type'        => 'integer',
					'description' => 'Maximum number of tasks to return (default 50, max 200).',
				],
				'offset'  => [
					'type'        => 'integer',
					'description' => 'Number of tasks to skip, for pagination (default 0).',
				],
			],
		];
	}

	public function __invoke(
		?int $site_id = null,
		?string $type = null,
		?bool $enabled = null,
		int $limit = 50,
		int $offset = 0
	): array
	{
		$user    = $this->getUser();
		$isSuper = (bool) $user->getPrivilege('panopticon.super');

		if (!$isSuper)
		{
			if ($site_id === null || $site_id <= 0)
			{
				throw new \RuntimeException(
					'Listing tasks requires Super User privileges; other users must provide a site_id they administer.'
				);
			}

			if (!$user->authorise('panopticon.admin', $site_id))
			{
				throw new \RuntimeException('You do not have admin permission on this site.');
			}
		}

		/** @var TaskModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Task');

		if ($site_id !== null && $site_id > 0)
		{
			$model->setState('site_id', $site_id);
		}

		if ($type !== null && $type !== '')
		{
			$model->setState('type', $type);
		}

		if ($enabled !== null)
		{
			$model->setState('enabled', $enabled ? 1 : 0);
		}

		$limit  = max(0, min(200, $limit));
		$offset = max(0, $offset);

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

		return [
			'tasks'      => $tasks,
			'pagination' => [
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			],
		];
	}
}
