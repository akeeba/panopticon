<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
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
use Akeeba\Panopticon\Task\Trait\EnqueueJoomlaUpdateTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Exception;

#[AsTask(
	name: 'joomlaupdatedirector',
	description: 'PANOPTICON_TASKTYPE_JOOMLAUPDATEDIRECTOR'
)]
class JoomlaUpdateDirector extends AbstractCallback
{
	use EnqueueJoomlaUpdateTrait;
	use SiteNotificationEmailTrait;
	use EmailSendingTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$limitStart = (int) $storage->get('limitStart', 0);
		$limit      = (int) $storage->get('limit', $params->get('limit', 100));
		$force      = (bool) $storage->get('force', $params->get('force', false));
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
			$this->logger->info('No more sites in need of automatic Joomla! core updates / update notifications.');

			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'Found a further %d site(s) to process for automatic Joomla! core updates / update notifications.',
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
				$updateTask = $site->getJoomlaUpdateTask();

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
							'Site #%d: Joomla! Update is currently running; skipping over.',
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
							'Site #%d: Joomla! Update to version %s is already scheduled.',
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

			// Disable all pending 'joomlaupdate' tasks for these sites.
			$query = $db->getQuery(true)
				->update($db->quoteName('#__tasks'))
				->set($db->quoteName('enabled') . ' = 0')
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

			// Log a report entry: we found an update for the site
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

			// Process the update action for the site
			$updateAction = $siteConfig->get("config.core_update.install", '')
				?: $this->container->appConfig->get('tasks_coreupdate_install', 'patch');
			$updateAction = $this->processUpdateAction($updateAction, $siteConfig);

			switch ($updateAction)
			{
				case "none":
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
					$this->logger->info(
						sprintf(
							'Site %d (%s) is configured to only send an email about Joomla! %s availability.',
							$id,
							$site->name,
							$siteConfig->get('core.latest.version')
						)
					);

					// Do I have to send an email?
					if (!$this->mustSchedule($site, true))
					{
						continue 2;
					}

					$this->sendEmail('joomlaupdate_found', $site);
					break;

				case "update":
					$this->logger->info(
						sprintf(
							'Site %d (%s) will be queued for update to Joomla! %s.',
							$id,
							$site->name,
							$siteConfig->get('core.latest.version')
						)
					);

					// Do I have to enqueue?
					if (!$this->mustSchedule($site, false))
					{
						continue 2;
					}

					// Send email
					$this->sendEmail('joomlaupdate_will_install', $site);

					// Enqueue task
					$this->enqueueJoomlaUpdate($site, $this->container);
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

				return $current->major() === $current->minor() ? "update" : "email";
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
		if ($site->isJoomlaUpdateTaskRunning())
		{
			return false;
		}

		// We cannot schedule / send emails if the last time the director run the same update was available.
		$lastLatestVersion = $siteConfig->get('director.joomlaupdate.lastLatestVersion');
		$latestVersion     = $siteConfig->get('core.latest.version');
		$mustSchedule      = $latestVersion != $lastLatestVersion;

		// When scheduling updates we must make sure there is no update task to the same version already active.
		if ($mustSchedule && !$emailOnly && $site->isJoomlaUpdateTaskScheduled())
		{
			$task       = $site->getJoomlaUpdateTask();
			$taskParams = ($task->params instanceof Registry) ? $task->params : new Registry($task->params ?? '{}');
			$toVersion  = $taskParams->get('toVersion');

			$mustSchedule = $toVersion != $latestVersion;
		}

		if (!$mustSchedule)
		{
			return false;
		}

		$siteConfig->set('director.joomlaupdate.lastLatestVersion', $latestVersion);
		$site->setFieldValue('config', $siteConfig->toString());
		$site->save();

		return true;
	}

}
