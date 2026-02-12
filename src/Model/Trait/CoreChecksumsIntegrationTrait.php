<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Awf\User\User;
use DateInterval;
use DateTimeZone;
use Exception;

/**
 * Trait for core file integrity checksum integration in the Site model.
 *
 * @since  1.3.0
 */
trait CoreChecksumsIntegrationTrait
{
	/**
	 * Enqueue a run-once core checksums check task for this site.
	 *
	 * @param   User|null  $user  The user who initiated the check
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function coreChecksumsScanEnqueue(?User $user = null): void
	{
		$tasks = $this->coreChecksumsGetEnqueuedTasks();

		if ($tasks->count())
		{
			$task = $tasks->first();
		}
		else
		{
			$task = Task::getTmpInstance('', 'Task', $this->container);
		}

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

		$runDateTime = $this->container->dateFactory('now', $tz);
		$runDateTime->add(new DateInterval('PT2S'));

		$task->save(
			[
				'site_id'         => $this->getId(),
				'type'            => 'corechecksums',
				'params'          => json_encode(
					[
						'run_once'             => 'disable',
						'enqueued_checksums'   => 1,
						'initiatingUser'       => $user?->getId(),
					]
				),
				'cron_expression' => $runDateTime->minute . ' ' . $runDateTime->hour . ' ' . $runDateTime->day . ' ' .
				                     $runDateTime->month . ' ' . $runDateTime->dayofweek,
				'enabled'         => 1,
				'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
				'last_execution'  => (clone $runDateTime)->sub(new DateInterval('PT1M'))->toSql(),
				'last_run_end'    => null,
				'next_execution'  => $runDateTime->toSql(),
				'locked'          => null,
				'priority'        => 1,
			]
		);
	}

	/**
	 * Get the stored list of modified core files from the site config.
	 *
	 * @return  array
	 * @since   1.3.0
	 */
	public function coreChecksumsGetModifiedFiles(): array
	{
		return $this->getConfig()->get('core.coreChecksums.modifiedFiles', []) ?: [];
	}

	/**
	 * Get the last core checksums check timestamp.
	 *
	 * @return  int|null
	 * @since   1.3.0
	 */
	public function coreChecksumsGetLastCheck(): ?int
	{
		return $this->getConfig()->get('core.coreChecksums.lastCheck', null);
	}

	/**
	 * Get enqueued (run-once) core checksums tasks that are not currently running.
	 *
	 * @return  \Awf\Mvc\DataModel\Collection
	 * @since   1.3.0
	 */
	private function coreChecksumsGetEnqueuedTasks(): \Awf\Mvc\DataModel\Collection
	{
		return $this->getSiteSpecificTasks('corechecksums')
			->filter(
				function (Task $task)
				{
					$params = $task->getParams();

					// Must not be running, or waiting to run
					if (in_array(
						$task->last_exit_code, [
							Status::INITIAL_SCHEDULE->value,
							Status::WILL_RESUME->value,
							Status::RUNNING->value,
						]
					))
					{
						return false;
					}

					// Must be a run-once task
					if (empty($params->get('run_once')))
					{
						return false;
					}

					// Must be a generated task, not a user-defined schedule
					if (empty($params->get('enqueued_checksums')))
					{
						return false;
					}

					return true;
				}
			);
	}
}
