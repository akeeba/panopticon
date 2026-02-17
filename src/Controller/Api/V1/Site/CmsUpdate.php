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
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task as TaskModel;
use Awf\Registry\Registry;
use DateInterval;
use DateTimeZone;

/**
 * API handler for POST /v1/site/:id/cmsupdate — schedule a CMS update for the site.
 *
 * @since  1.4.0
 */
class CmsUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$body = $this->getJsonBody();

		$force = (bool) ($body['force'] ?? false);
		$user  = $this->container->userManager->getUser();

		$taskType = match ($site->cmsType())
		{
			CMSType::JOOMLA    => 'joomlaupdate',
			CMSType::WORDPRESS => 'wordpressupdate',
			default            => null,
		};

		if ($taskType === null)
		{
			$this->sendJsonError(400, 'Unsupported CMS type for update scheduling.');
		}

		try
		{
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
			$params->set('force', $force);
			$params->set('toVersion', $site->getConfig()->get('core.latest.version'));
			$params->set('initiatingUser', $user->getId());

			$task->params         = $params->toString();
			$task->storage        = '{}';
			$task->enabled        = 1;
			$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
			$task->locked         = null;

			try
			{
				$tz = $this->container->appConfig->get('timezone', 'UTC');

				// Validate the configured timezone
				new DateTimeZone($tz);
			}
			catch (\Exception)
			{
				$tz = 'UTC';
			}

			$siteConfig = $site->getConfig() ?? new Registry();

			switch ($siteConfig->get('config.core_update.when', 'immediately'))
			{
				default:
				case 'immediately':
					$then = $this->container->dateFactory('now', $tz);
					$then->add(new DateInterval('PT2S'));

					$task->cron_expression = $then->minute . ' ' . $then->hour . ' '
						. $then->day . ' ' . $then->month . ' ' . $then->dayofweek;
					$task->last_execution  = (clone $then)->sub(new DateInterval('PT1M'))->toSql();
					$task->last_run_end    = null;
					$task->next_execution  = $then->toSql();
					break;

				case 'time':
					$hour   = max(0, min((int) $siteConfig->get('config.core_update.time.hour', 0), 23));
					$minute = max(0, min((int) $siteConfig->get('config.core_update.time.minute', 0), 59));
					$now    = $this->container->dateFactory('now', $tz);
					$then   = (clone $now)->setTime($hour, $minute, 0);

					// If the selected time of day is in the past, go forward one day
					if ($now->diff($then)->invert)
					{
						$then->add(new DateInterval('P1D'));
					}

					$task->cron_expression =
						$then->minute . ' ' . $then->hour . ' ' . $then->day . ' ' . $then->month . ' *';
					$task->next_execution  = $then->toSql();
					break;
			}

			$task->setState('disable_next_execution_recalculation', 1);
			$task->save();

			// For Joomla, update lastAutoUpdateVersion in site config
			if ($site->cmsType() === CMSType::JOOMLA)
			{
				$siteConfig->set('core.lastAutoUpdateVersion', $siteConfig->get('core.latest.version'));
				$site->config = $siteConfig->toString();
				$site->save();
			}
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to schedule CMS update: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'CMS update scheduled successfully.');
	}
}
