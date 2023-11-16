<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Awf\User\User;
use DateTimeZone;
use Exception;

trait EnqueueJoomlaUpdateTrait
{
	/**
	 * Enqueues a Joomla site for automatic update to a newer Joomla version.
	 *
	 * @param Site      $site      The object of the site to enqueue for update
	 * @param Container $container The application container
	 * @param bool      $force     Should I force the update (even if it's the same version as already installed?)
	 *
	 * @return  void
	 */
	private function enqueueJoomlaUpdate(Site $site, Container $container, bool $force = false, ?User $user = null): void
	{
		/** @var Task $task */
		$task = $container->mvcFactory->makeTempModel('Task');

		// Try to load an existing task
		try
		{
			$task->findOrFail([
				'site_id' => $site->id,
				'type'    => 'joomlaupdate',
			]);
		}
		catch (\RuntimeException $e)
		{
			$task->reset();
			$task->site_id = $site->id;
			$task->type    = 'joomlaupdate';
		}

		// Set up the task
		$params = new Registry();
		$params->set('run_once', 'disable');
		$params->set('force', $force);
		$params->set('toVersion', $site->getConfig()->get('core.latest.version'));
		$params->set('initiatingUser', $user?->getId());

		$task->params         = $params->toString();
		$task->storage        = '{}';
		$task->enabled        = 1;
		$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
		$task->locked         = null;

		try
		{
			$tz = $this->container->appConfig->get('timezone', 'UTC');

			// Do not remove. This tests the validity of the configured timezone.
			new DateTimeZone($tz);
		}
		catch (Exception)
		{
			$tz = 'UTC';
		}

		$siteConfig = ($site->config instanceof Registry) ? $site->config : new Registry($site->config ?? '{}');
		switch ($siteConfig->get('config.core_update.when', 'immediately'))
		{
			default:
			case 'immediately':
				$then = $this->container->dateFactory('now', $tz);
				$then->add(new \DateInterval('PT2S'));

				$task->cron_expression = $then->minute . ' ' . $then->hour . ' ' . $then->day . ' ' . $then->month . ' '
					. $then->dayofweek;
				$task->last_execution = (clone $then)->sub(new \DateInterval('PT1M'))->toSql();
				$task->last_run_end = null;
				$task->next_execution = $then->toSql();
				break;

			case 'time':
				$hour   = max(0, min((int) $siteConfig->get('config.core_update.time.hour', 0), 23));
				$minute = max(0, min((int) $siteConfig->get('config.core_update.time.minute', 0), 59));
				$now    = $this->container->dateFactory('now', $tz);
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