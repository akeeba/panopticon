<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

#[AsTask(
	name: 'joomlaupdatedirector',
	description: 'PANOPTICON_TASKTYPE_JOOMLAUPDATEDIRECTOR'
)]
class JoomlaUpdateDirector extends AbstractCallback implements LoggerAwareInterface
{
	use LoggerAwareTrait;

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
			$this->logger->info('No more sites in need of automatic updates / update notifications.');

			$db->unlockTables();
			$db->setQuery('SET autocommit = 1')->execute();

			return Status::OK->value;
		}

		$this->logger->info(sprintf(
			'Found a further %d site(s) to process for automatic updates / update notifications.',
			count($siteIDs)
		));

		// Set `core.lastAutoUpdateVersion` to `core.latest.version` for all sites to be processed
		$query = $db->getQuery(true)
			->update($db->quoteName('#__sites'));
		$query->set(
			$db->quoteName('config') . '= JSON_SET(' . $db->quoteName('config') . ',' .
			$db->quote('$.core.lastAutoUpdateVersion') . ',' . $query->jsonPointer('config', '$.core.latest.version') . ')'
		)
			->where($db->quoteName('id') . ' IN(' . implode(',', $siteIDs) . ')');
		$db->setQuery($query)->execute();

		// Disable all pending 'joomlaupdate' tasks for these sites.
		$query = $db->getQuery(true)
			->update($db->quoteName('#__tasks'))
			->set($db->quoteName('enabled') . ' = 0')
			->where($db->quoteName('site_id') . ' IN(' . implode(',', $siteIDs) . ')');
		$db->setQuery($query)->execute();

		// End the transaction
		$db->setQuery('COMMIT')->execute();
		$db->unlockTables();
		$db->setQuery('SET autocommit = 1')->execute();

		/** @var Site $site */
		$site = Model::getTmpInstance('', 'Site', $this->container);

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

			$siteConfig   = ($site->config instanceof Registry) ? $site->config : new Registry($site->config ?? '{}');
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

					// Send email
					$this->sendEmail('joomlaupdate_will_install', $site);

					// Enqueue task
					$this->enqueueJoomlaUpdate($site);
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
		$query->where([
			// `enabled` = 1
			$db->quoteName('enabled') . ' = 1',
			// `config` -> '$.core.canUpgrade'
			$query->jsonPointer('config', '$.core.canUpgrade'),
		]);

		if (!$force)
		{
			$query->where([
				// `config` -> '$.core.current.version' != `config` -> '$.core.latest.version'
				$query->jsonPointer('config', '$.core.current.version') . ' != ' .
				$query->jsonPointer('config', '$.core.latest.version'),
				// `config` -> '$.config.core_update.install' != 'none'
				$query->jsonPointer('config', '$.config.core_update.install') . ' != ' . $db->quote('none'),
			]);
			//   AND (
			//        `config` -> '$.core.lastAutoUpdate' IS NULL
			//        OR `config` -> '$.core.lastAutoUpdateVersion' != `config` -> '$.core.latest.version'
			//    )
			$query->extendWhere('AND', [
				$query->jsonPointer('config', '$.core.lastAutoUpdateVersion') . ' IS NULL',
				$query->jsonPointer('config', '$.core.lastAutoUpdateVersion') . ' != ' .
				$query->jsonPointer('config', '$.core.latest.version'),
			], 'OR');
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

	private function sendEmail(string $mailtemplate, Site $site, array $permissions = [
		'panopticon.super', 'panopticon.manage',
	]): void
	{
		$this->logger->debug(
			sprintf(
				'Enqueing email template ‘%s’ for site %d (%s)',
				$mailtemplate, $site->id, $site->name
			)
		);

		$siteConfig = ($site->config instanceof Registry) ? $site->config : new Registry($site->config ?? '{}');

		$variables = [
			'NEW_VERSION' => $siteConfig->get('core.latest.version'),
			'OLD_VERSION' => $siteConfig->get('core.current.version'),
			'SITE_NAME'   => $site->name,
		];

		$cc = array_map(
			function (string $item) {
				$item = trim($item);

				if (!str_contains($item, '<'))
				{
					return [$item, ''];
				}

				[$name, $email] = explode('<', $item, 2);
				$name  = trim($name);
				$email = trim(
					str_contains($email, '>')
						? substr($email, 0, strrpos($email, '>') - 1)
						: $email
				);

				return [$email, $name];
			},
			explode(',', $siteConfig->get('config.core_update.email.cc', ''))
		);

		$data = new Registry();
		$data->set('template', $mailtemplate);
		$data->set('email_variables', $variables);
		$data->set('permissions', $permissions);
		$data->set('email_cc', $cc);

		$queueItem = new QueueItem(
			$data->toString(),
			QueueTypeEnum::MAIL->value,
			$site->id
		);
		$queue     = $this->container->queueFactory->makeQueue(QueueTypeEnum::MAIL->value);

		$queue->push($queueItem, 'now');
	}

	private function enqueueJoomlaUpdate(Site $site): void
	{
		/** @var Task $task */
		$task = Model::getTmpInstance('', 'Task', $this->container);

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
		$task->params         = $params->toString();
		$task->storage        = '{}';
		$task->enabled        = 1;
		$task->last_exit_code = Status::INITIAL_SCHEDULE->value;
		$task->locked         = null;

		$siteConfig = ($site->config instanceof Registry) ? $site->config : new Registry($site->config ?? '{}');
		switch ($siteConfig->get('config.core_update.when', 'immediately'))
		{
			default:
			case 'immediately':
				$task->cron_expression = '* * * * *';
				$then                  = new Date('now', 'UTC');
				break;

			case 'time':
				$hour   = max(0, min((int) $siteConfig->get('config.core_update.time.hour', 0), 23));
				$minute = max(0, min((int) $siteConfig->get('config.core_update.time.minute', 0), 59));
				$now    = new Date('now', 'UTC');
				$then   = (clone $now)->setTime($hour, $minute, 0);

				// If the selected time of day is in the past, go forward one day
				if ($now->diff($then)->invert)
				{
					$then->add(new \DateInterval('P1D'));
				}

				$task->cron_expression = $then->minute . ' ' . $then->hour . ' ' . $then->day . ' ' . $then->month . ' *';
				break;
		}

		$task->next_execution = $then->toSql();

		// TODO WHY THE HELL DOES IT DELETE AN EXISTING TASK INSTEAD OF UPDATING IT?!!
		$task->save();
	}
}