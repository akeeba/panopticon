<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Registry\Registry;

trait EnqueueExtensionUpdateTrait
{
	private function scheduleExtensionsUpdateForSite(Site $site, Container $container, bool $force = false): void
	{
		/** @var Task $task */
		$task = Model::getTmpInstance('', 'Task', $container);

		// Try to load an existing task
		try
		{
			$task->findOrFail([
				'site_id' => $site->id,
				'type'    => 'extensionsupdate',
			]);
		}
		catch (\RuntimeException $e)
		{
			$task->reset();
			$task->site_id = $site->id;
			$task->type    = 'extensionsupdate';
		}

		// Set up the task
		$params = new Registry();
		$params->set('run_once', 'disable');
		$params->set('force', $force);

		$task->params         = $params->toString();
		$task->storage        = '{}';
		$task->enabled        = 1;
		$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
		$task->locked         = null;

		$siteConfig = ($site->config instanceof Registry) ? $site->config : new Registry($site->config ?? '{}');
		switch ($siteConfig->get('config.extensions_update.when', 'immediately'))
		{
			default:
			case 'immediately':
				$task->cron_expression = '* * * * *';
				$then                  = new Date('now', 'UTC');
				break;

			case 'time':
				$hour   = max(0, min((int)$siteConfig->get('config.extensions_update.time.hour', 0), 23));
				$minute = max(0, min((int)$siteConfig->get('config.extensions_update.time.minute', 0), 59));
				$now    = new Date('now', 'UTC');
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

		$task->save();
	}

	private function enqueueExtensionUpdate(Site $site, int $extensionId, string $effectivePreference = 'major'): bool
	{
		// Enqueue necessary updates
		$queueKey = sprintf('extensions.%d', $site->id);
		$queue    = $this->container->queueFactory->makeQueue($queueKey);

		// Avoid enqueueing the same extension multiple times
		$numItems = $queue->countByCondition(['data' => $extensionId, 'siteId' => (int) $site->id]);

		if ($numItems > 0)
		{
			return false;
		}

		$queueItem = new QueueItem(
			data: (object) [
				'id'   => $extensionId,
				'mode' => match ($effectivePreference)
				{
					'email' => 'email',
					default => 'update'
				},
			],
			queueType: $queueKey,
			siteId: $site->id
		);

		$queue->push($queueItem, 'now');

		return true;
	}
}