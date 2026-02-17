<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task as TaskModel;
use Awf\Registry\Registry;

/**
 * API handler for POST /v1/site/:id/extensions — schedule an extensions refresh.
 *
 * @since  1.4.0
 */
class ExtensionsRefresh extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');

		try
		{
			/** @var TaskModel $task */
			$task = $this->container->mvcFactory->makeTempModel('Task');

			// Try to load an existing task for this site
			try
			{
				$task->findOrFail([
					'site_id' => $site->getId(),
					'type'    => 'refreshinstalledextensions',
				]);
			}
			catch (\RuntimeException)
			{
				$task->reset();
				$task->site_id = $site->getId();
				$task->type    = 'refreshinstalledextensions';
			}

			$params = new Registry();
			$params->set('run_once', 'disable');
			$params->set('force', true);
			$params->set('forceUpdates', true);

			$task->params         = $params->toString();
			$task->storage        = '{}';
			$task->enabled        = 1;
			$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
			$task->locked         = null;
			$task->cron_expression = '* * * * *';

			$now = $this->container->dateFactory('now', 'UTC');

			$task->next_execution = $now->toSql();

			$task->setState('disable_next_execution_recalculation', 1);
			$task->save();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to schedule extensions refresh: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Extensions refresh scheduled successfully.');
	}
}
