<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\EnqueueWordPressUpdateTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Exception;

#[AsTask(
	name: 'wordpressupdatedirector',
	description: 'PANOPTICON_TASKTYPE_WORDPRESSUPDATEDIRECTOR'
)]
class WordPressUpdateDirector extends AbstractCallback
{
	use EnqueueWordPressUpdateTrait;
	use SiteNotificationEmailTrait;
	use EmailSendingTrait;
	use SaveSiteTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$limitStart = (int) $storage->get('limitStart', 0);
		$limit      = (int) $storage->get('limit', $params->get('limit', 100));
		$force      = (bool) $storage->get('force', $params->get('force', false));
		$forceQueue = (bool) $storage->get('force_queue', $params->get('force_queue', false));
		$filterIDs  = $storage->get('filter.ids', $params->get('ids', []));

		/**
		 * Reasoning behind this code:
		 *
		 * “The correct way to use LOCK TABLES and UNLOCK TABLES with transactional tables, such as InnoDB tables, is to
		 * begin a transaction with SET autocommit = 0 (not START TRANSACTION) followed by LOCK TABLES, and to not call
		 * UNLOCK TABLES until you commit the transaction explicitly.”
		 *
		 * This is meant to avoid deadlocks.
		 *
		 * @see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		 */
		// Lock the #__sites and #__tasks tables
		$db = $this->container->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$sql = 'LOCK TABLES ' . $db->quoteName('#__sites') . ' WRITE, '
		       . $db->quoteName('#__tasks') . ' WRITE, '
		       . $db->quoteName('#__queue') . ' WRITE';
		$db->setQuery($sql)->execute();

		$siteIDs = $this->getSiteIDs($limitStart, $limit, $force, $filterIDs);

		if (empty($siteIDs))
		{
			$this->logger->info('No more sites in need of automatic WordPress core updates / update notifications.');

			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'Found a further %d site(s) to process for automatic WordPress core updates / update notifications.',
				count($siteIDs)
			)
		);

		// Filter site IDs so that those with manually enabled, or currently executing, update tasks are not disrupted
		$siteIDs = array_filter(
			$siteIDs,
			function (int $siteId) {
				/** @var Site $site */
				$site = $this->container->mvcFactory->makeTempModel('Site');

				try
				{
					$site->findOrFail($siteId);
				}
				catch (Exception)
				{
					$this->logger->debug(
						sprintf(
							'Site #%d does not exist.',
							$siteId
						)
					);

					return false;
				}

				/** @var Task|null $updateTask */
				$updateTask = $site->getWordPressUpdateTask();

				if (empty($updateTask))
				{
					return true;
				}

				if (in_array(
					$updateTask->last_exit_code, [
						Status::WILL_RESUME->value,
						Status::INITIAL_SCHEDULE->value,
						Status::RUNNING->value,
					]
				))
				{
					$this->logger->debug(
						sprintf(
							'Site #%d: WordPress core update is currently running; skipping over.',
							$siteId
						)
					);

					return false;
				}

				$actualLatestVersion = $site->getConfig()->get('core.latest.version');
				$taskLatestVersion   = $updateTask->getParams()->get('toVersion');

				if ($actualLatestVersion === $taskLatestVersion)
				{
					$this->logger->debug(
						sprintf(
							'Site #%d: WordPress core update to version %s is already scheduled.',
							$siteId,
							$actualLatestVersion
						)
					);
				}

				return true;
			}
		);

		if (count($siteIDs))
		{
			// Set `core.lastAutoUpdateVersion` to `core.latest.version` for all sites to be processed
			$query = $db->getQuery(true)
				->update($db->quoteName('#__sites'));
			$query->set(
				$db->quoteName('config') . '= JSON_SET(' . $db->quoteName('config') . ',' .
				$db->quote('$.core.lastAutoUpdateVersion') . ',' . $query->jsonExtract(
					$db->quoteName('config'), '$.core.latest.version'
				) . ')'
			)
				->where($db->quoteName('id') . ' IN(' . implode(',', $siteIDs) . ')');
			$db->setQuery($query)->execute();

			// Disable all pending 'wordpressupdate' tasks for these sites.
			$query = $db->getQuery(true)
				->update($db->quoteName('#__tasks'))
				->set($db->quoteName('enabled') . ' = 0')
                ->where($db->quoteName('type').' like '.$db->quote('wordpressupdate'))
				->where($db->quoteName('site_id') . ' IN(' . implode(',', $siteIDs) . ')');
			$db->setQuery($query)->execute();
		}

		// End the transaction
		$db->setQuery('COMMIT')->execute();
		$db->unlockTables();
		$db->setQuery('SET autocommit = 1')->execute();

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		foreach ($siteIDs as $id)
		{
			$site->reset();

			try
			{
				$site->findOrFail($id);
			}
			catch (\RuntimeException $e)
			{
				$this->logger->notice(
					sprintf('Site %d not found; skipping.', $id)
				);

				continue;
			}

			// Get the site's configuration
			$siteConfig = $site->getConfig() ?? new Registry();

			// Process the update action for the site
			$updateAction = $siteConfig->get("config.core_update.install", '')
				?: $this->container->appConfig->get('tasks_coreupdate_install', 'patch');
			$updateAction = $this->processUpdateAction($updateAction, $siteConfig);

			switch ($updateAction)
			{
				case "none":
					if (!$this->mustSchedule($site, true))
					{
						continue 2;
					}

					// Log a report entry: we found an update for the site
					$this->logCoreUpdateFoundToSiteReports($site, $siteConfig);

					$this->logger->info(
						sprintf(
							'Site %d (%s) is configured to neither update, nor send emails.',
							$id,
							$site->name
						)
					);
					break;

				case "email":
				default:
					// Do I have to send an email?
					if (!$forceQueue && !$this->mustSchedule($site, true))
					{
						continue 2;
					}

					// Log a report entry: we found an update for the site
					$this->logCoreUpdateFoundToSiteReports($site, $siteConfig);

					$this->logger->info(
							sprintf(
								'Site %d (%s) is configured to only send an email about WordPress %s availability.',
								$id,
								$site->name,
								$siteConfig->get('core.latest.version')
							)
						);

					$this->sendEmail('wordpressupdate_found', $site);
					break;

				case "update":
					// Do I have to enqueue?
					if (!$forceQueue && !$this->mustSchedule($site, false))
					{
						continue 2;
					}

					$this->logger->info(
						sprintf(
							'Site %d (%s) will be queued for update to WordPress! %s.',
							$id,
							$site->name,
							$siteConfig->get('core.latest.version')
						)
					);

					// Log a report entry: we found an update for the site
					$this->logCoreUpdateFoundToSiteReports($site, $siteConfig);

					// Send email
					$this->sendEmail('wordpressupdate_will_install', $site);

					// Enqueue task
					$this->enqueueWordPressUpdate($site, $this->container);
					break;
			}
		}

		$storage->set('limitStart', $limitStart + $limit);

		return Status::WILL_RESUME->value;
	}

	private function getSiteIDs(int $limitStart = 0, int $limit = 100, bool $force = false, array $ids = []): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__sites'));
		$query->where(
			[
				// `enabled` = 1
				$db->quoteName('enabled') . ' = 1',
				// `config` -> '$.core.canUpgrade'
				$query->jsonExtract($db->quoteName('config'), '$.core.canUpgrade'),
				// Only fetch WordPress sites
				$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote('wordpress')
			]
		);

		if (!$force)
		{
			$query->where(
				[
					// `config` -> '$.core.current.version' != `config` -> '$.core.latest.version'
					$query->jsonExtract($db->quoteName('config'), '$.core.current.version') . ' != ' .
					$query->jsonExtract($db->quoteName('config'), '$.core.latest.version'),
					// `config` -> '$.config.core_update.install' != 'none'
					$query->jsonExtract($db->quoteName('config'), '$.config.core_update.install') . ' != ' . $db->quote(
						'none'
					),
				]
			);
			//   AND (
			//        `config` -> '$.core.lastAutoUpdate' IS NULL
			//        OR `config` -> '$.core.lastAutoUpdateVersion' != `config` -> '$.core.latest.version'
			//    )
			$query->extendWhere(
				'AND', [
				$query->jsonExtract($db->quoteName('config'), '$.core.lastAutoUpdateVersion') . ' IS NULL',
				$query->jsonExtract($db->quoteName('config'), '$.core.lastAutoUpdateVersion') . ' != ' .
				$query->jsonExtract($db->quoteName('config'), '$.core.latest.version'),
			], 'OR'
			);
		}

		$ids = ArrayHelper::toInteger($ids);
		$ids = array_filter($ids, fn($x) => !empty($x));

		if (!empty($ids))
		{
			$query->where(
				$db->quoteName('id') . ' IN (' . implode(',', $ids) . ')'
			);
		}

		return $db->setQuery($query, $limitStart, $limit)->loadColumn() ?: [];
	}

	private function processUpdateAction(string $updateAction, Registry $siteConfig): string
	{
		switch ($updateAction)
		{
			case "none":
			case "email":
				return $updateAction;

			case "major":
				return "update";

			default:
			case "patch":
				$current = Version::create($siteConfig->get('core.current.version'));
				$latest  = Version::create($siteConfig->get('core.latest.version'));

				$shortCurrent = $current->major() . '.' . $current->minor();
				$shortLatest  = $latest->major() . '.' . $latest->minor();

				return $shortCurrent === $shortLatest ? "update" : "email";

			case "minor":
				$current = Version::create($siteConfig->get('core.current.version'));
				$latest  = Version::create($siteConfig->get('core.latest.version'));

				return $current->major() === $latest->major() ? "update" : "email";
				break;
		}
	}

	private function sendEmail(
		string $mailtemplate, Site $site, array $permissions = [
		'panopticon.super',
		'panopticon.manage',
	]
	): void
	{
		$this->logger->debug(
			sprintf(
				'Enqueuing email template ‘%s’ for site %d (%s)',
				$mailtemplate, $site->id, $site->name
			)
		);

		$siteConfig = $site->getConfig() ?? new Registry();

		$variables = [
			'NEW_VERSION' => $siteConfig->get('core.latest.version'),
			'OLD_VERSION' => $siteConfig->get('core.current.version'),
			'SITE_NAME'   => $site->name,
			'SITE_URL'    => $site->getBaseUrl(),
		];

		try
		{
			$config = @json_decode($siteConfig->toString());
		}
		catch (Exception $e)
		{
			$config = null;
		}

		$cc = $this->getSiteNotificationEmails($config);

		$data = new Registry();
		$data->set('template', $mailtemplate);
		$data->set('email_variables', $variables);
		$data->set('permissions', $permissions);
		$data->set('email_cc', $cc);


		$this->enqueueEmail($data, $site->id, 'now');
	}

	private function mustSchedule(Site $site, bool $emailOnly): bool
	{
		$siteConfig = $site->getConfig() ?? new Registry();

		// We must not send emails or schedule anything if an update is already running
		if ($site->isWordPressUpdateTaskRunning())
		{
			return false;
		}

		// We cannot schedule / send emails if the last time the director run the same update was available.
		$lastLatestVersion = $siteConfig->get('director.wordpressupdate.lastLatestVersion');
		$latestVersion     = $siteConfig->get('core.latest.version');
		$mustSchedule      = $latestVersion != $lastLatestVersion;

		// When scheduling updates we must make sure there is no update task to the same version already active.
		if ($mustSchedule && !$emailOnly && $site->isWordPressUpdateTaskScheduled())
		{
			$task       = $site->getWordPressUpdateTask();
			$taskParams = ($task->params instanceof Registry) ? $task->params : new Registry($task->params ?? '{}');
			$toVersion  = $taskParams->get('toVersion');

			$mustSchedule = $toVersion != $latestVersion;
		}

		if (!$mustSchedule)
		{
			return false;
		}

		$this->saveSite(
			$site,
			function (Site $site) use ($latestVersion) {
				$siteConfig = $site->getConfig() ?? new Registry();
				$siteConfig->set('director.wordpressupdate.lastLatestVersion', $latestVersion);
				$site->setFieldValue('config', $siteConfig->toString());
			}
		);

		return true;
	}

	/**
	 * Add a Site Reports log entry about finding a new CMS version.
	 *
	 * @param   Site      $site
	 * @param   Registry  $siteConfig
	 *
	 * @return  void
	 * @since   1.2.2
	 */
	private function logCoreUpdateFoundToSiteReports(Site $site, Registry $siteConfig): void
	{
		try
		{
			Reports::fromCoreUpdateFound(
				$site->getId(),
				$siteConfig->get('core.current.version'),
				$siteConfig->get('core.latest.version')
			)->save();
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				sprintf(
					'Problem saving report log entry [%s:%s]: %d %s',
					$e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage()
				)
			);
		}
	}
}
