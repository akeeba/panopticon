<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task as TaskModel;
use Awf\Registry\Registry;

/**
 * API handler for POST /v1/site/:id/extensions/scheduleupdate/:extId — enqueue an extension for update.
 *
 * @since  1.4.0
 */
class ExtensionScheduleUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'run');

		if ($extId <= 0)
		{
			$this->sendJsonError(400, 'Invalid extension ID.');
		}

		$user = $this->container->userManager->getUser();

		try
		{
			$queuePattern = match ($site->cmsType())
			{
				CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
				CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
				default            => null,
			};

			if ($queuePattern === null)
			{
				$this->sendJsonError(400, 'Unsupported CMS type for extension updates.');
			}

			$queueKey = sprintf($queuePattern, $site->getId());
			$queue    = $this->container->queueFactory->makeQueue($queueKey);

			// Avoid enqueueing the same extension multiple times
			$numItems = $queue->countByCondition(['data.id' => $extId, 'siteId' => $site->getId()]);

			if ($numItems > 0)
			{
				$this->sendJsonResponse(null, 200, 'Extension is already queued for update.');

				return;
			}

			$queueItem = new QueueItem(
				data: (object) [
					'id'             => $extId,
					'mode'           => 'update',
					'initiatingUser' => $user->getId(),
				],
				queueType: $queueKey,
				siteId: $site->getId()
			);

			$queue->push($queueItem, 'now');

			// Ensure the extensions update task exists and is scheduled
			$this->scheduleExtensionsUpdateTask($site);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to schedule extension update: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Extension update scheduled successfully.');
	}

	/**
	 * Ensure the extensions/plugins update task exists and is scheduled for this site.
	 *
	 * @param   \Akeeba\Panopticon\Model\Site  $site
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	private function scheduleExtensionsUpdateTask(\Akeeba\Panopticon\Model\Site $site): void
	{
		$taskType = match ($site->cmsType())
		{
			CMSType::JOOMLA    => 'extensionsupdate',
			CMSType::WORDPRESS => 'pluginsupdate',
			default            => null,
		};

		if ($taskType === null)
		{
			return;
		}

		/** @var TaskModel $task */
		$task = $this->container->mvcFactory->makeTempModel('Task');

		// Try to load an existing task
		try
		{
			$task->findOrFail([
				'site_id' => $site->getId(),
				'type'    => $taskType,
			]);
		}
		catch (\RuntimeException)
		{
			$task->reset();
			$task->site_id = $site->getId();
			$task->type    = $taskType;
		}

		// Set up the task
		$params = new Registry();
		$params->set('run_once', 'disable');
		$params->set('force', false);

		$task->params         = $params->toString();
		$task->storage        = '{}';
		$task->enabled        = 1;
		$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
		$task->locked         = null;

		$siteConfig = $site->getConfig() ?? new Registry();

		switch ($siteConfig->get('config.extensions_update.when', 'immediately'))
		{
			default:
			case 'immediately':
				$task->cron_expression = '* * * * *';
				$then                  = $this->container->dateFactory('now', 'UTC');
				break;

			case 'time':
				$hour   = max(0, min((int) $siteConfig->get('config.extensions_update.time.hour', 0), 23));
				$minute = max(0, min((int) $siteConfig->get('config.extensions_update.time.minute', 0), 59));
				$now    = $this->container->dateFactory('now', 'UTC');
				$then   = (clone $now)->setTime($hour, $minute, 0);

				// If the selected time of day is in the past, go forward one day
				if ($now->diff($then)->invert)
				{
					$then->add(new \DateInterval('P1D'));
				}

				$task->cron_expression =
					$then->minute . ' ' . $then->hour . ' ' . $then->day . ' ' . $then->month . ' *';
				break;
		}

		$task->next_execution = $then->toSql();

		$task->setState('disable_next_execution_recalculation', 1);
		$task->save();
	}
}
