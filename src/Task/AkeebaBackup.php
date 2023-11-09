<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;
use Awf\Text\Text;

#[AsTask(
	name: 'akeebabackup',
	description: 'PANOPTICON_TASKTYPE_AKEEBABACKUP'
)]
class AkeebaBackup extends AbstractCallback
{

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->findOrFail($task->site_id);

		// Add a site-specific logger
		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Load the task configuration parameters
		$params         = $task->params instanceof Registry ? $task->params : new Registry($task->params);
		$profile        = $params->get('profile_id', 1);
		$description    = $params->get('description', Text::_('PANOPTICON_BACKUPTASKS_LBL_DESCRIPTION_DEFAULT'));
		$comment        = $params->get('comment', '');
		$initiatingUser = $params->get('initiatingUser', 0);

		// Replace the variables in the description and comment
		$now          = $this->container->dateFactory();
		$replacements = [
			'{DATE_FORMAT_LC}'  => $now->format(Text::_('DATE_FORMAT_LC')),
			'{DATE_FORMAT_LC1}' => $now->format(Text::_('DATE_FORMAT_LC1')),
			'{DATE_FORMAT_LC2}' => $now->format(Text::_('DATE_FORMAT_LC2')),
			'{DATE_FORMAT_LC3}' => $now->format(Text::_('DATE_FORMAT_LC3')),
			'{DATE_FORMAT_LC4}' => $now->format(Text::_('DATE_FORMAT_LC4')),
			'{DATE_FORMAT_LC5}' => $now->format(Text::_('DATE_FORMAT_LC5')),
			'{DATE_FORMAT_LC6}' => $now->format(Text::_('DATE_FORMAT_LC6')),
			'{DATE_FORMAT_LC7}' => $now->format(Text::_('DATE_FORMAT_LC7')),
		];
		$description  = str_replace(array_keys($replacements), array_values($replacements), $description);
		$comment      = str_replace(array_keys($replacements), array_values($replacements), $comment);

		// Load the temporary storage
		$state          = $storage->get('state', 'init');
		$backupId       = $storage->get('backupID');
		$backupRecordId = $storage->get('backupRecordID');
		$archive        = $storage->get('archive');

		// Run the backup start or step, depending on our state
		if ($state === 'init')
		{
			$this->logger->info(
				sprintf(
					'Starting backup on site #%d (%s) with backup profile %d',
					$site->getId(),
					$site->name,
					$profile
				)
			);

			$result = $site->akeebaBackupStartBackup($profile, $description, $comment);
		}
		else
		{
			$this->logger->info(
				sprintf(
					'Continuing backup on site #%d (%s) with backup profile %d',
					$site->getId(),
					$site->name,
					$profile
				)
			);

			$result = $site->akeebaBackupStepBackup($backupId);
		}

		$storage->set('state', 'step');
		$storage->set('backupID', $result?->backupID ?: $backupId);
		$storage->set('backupRecordID', $result?->backupRecordID ?: $backupRecordId);
		$storage->set('archive', $result?->archive ?: $archive);

		// Log the backup tick results
		$this->logger->info(
			sprintf(
				'Received backup tick for site #%d (%s) and backup profile %d',
				$site->getId(),
				$site->name,
				$profile
			), (array) ($result?->data ?? [])
		);

		if (empty($backupId) && !empty($result?->backupID))
		{
			$this->logger->debug(sprintf('Got BackupID: %s', $result?->backupID));
		}
		elseif (!empty($backupId) && !empty($result?->backupID) && ($backupId != $result?->backupID))
		{
			$this->logger->debug(
				sprintf(
					'Uh-oh! Problem detected running a backup for site #%d (%s) and backup profile %d. We had already received the BackupID %s and now we received a completely different one, %s. The backup will be terminated to prevent a runaway backup condition with detrimental impact to the site and its server.',
					$site->getId(),
					$site->name,
					$profile,
					$backupId,
					$result?->backupID
				)
			);

			$errorMessage = sprintf(
				'BACKUP FAILURE: THE BACKUP FOR SITE #%d (%s) AND BACKUP PROFILE %d WAS RESTARTED ON THE SERVER.',
				$site->getId(),
				$site->name,
				$profile
			);

			// Log failed backup report
			try
			{
				$report = Reports::fromBackup(
					$site->id,
					$profile,
					false,
					[
						'backupId'     => $backupId,
						'backupRecord' => $backupRecordId,
						'archive'      => $archive,
						'message'      => $errorMessage,
					]
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception $e)
			{
				// Whatever
			}

			// Send email
			$this->sendEmail(
				'akeebabackup_fail',
				$site,
				[
					'PROFILE_ID'    => $profile,
					'BACKUPID'      => $backupId,
					'BACKUP_RECORD' => $backupRecordId,
					'ARCHIVE_NAME'  => $archive,
					'MESSAGE'       => $errorMessage,
				]
			);

			$this->backupRecordsCacheBuster($site);

			// Die.
			throw new \RuntimeException($errorMessage);
		}

		if (empty($backupRecordId) && !empty($result?->backupRecordID))
		{
			$this->logger->debug(sprintf('Got the new backup record ID: %s', $result?->backupRecordID));
		}

		if (empty($archive) && !empty($result?->archive))
		{
			$this->logger->debug(sprintf('Got the new archive name: %s', $result?->archive));
		}

		$rawData = $result?->data?->body?->data;

		$this->logger->debug(sprintf('Domain: %s', $rawData?->Domain ?? '(unknown)'));
		$this->logger->debug(sprintf('Step: %s', $rawData?->Step ?? '(unknown)'));
		$this->logger->debug(sprintf('Sub-step: %s', $rawData?->Substep ?? '(unknown)'));
		$this->logger->debug(sprintf('Progress: %0.2f%%', $rawData?->Progress ?? '(unknown)'));

		if (!empty($rawData?->Warnings) && is_array($rawData?->Warnings))
		{
			foreach ($rawData?->Warnings as $warning)
			{
				$this->logger->warning($warning);
			}
		}

		if (!$rawData?->HasRun && empty($rawData?->Error))
		{
			$this->logger->info('The backup finished successfully.');

			// Log successful backup report
			try
			{
				$report = Reports::fromBackup(
					$site->id,
					$profile,
					true,
					[
						'backupId'     => $backupId,
						'backupRecord' => $backupRecordId,
						'archive'      => $archive,
					]
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception $e)
			{
				// Whatever
			}

			// Send email
			$this->sendEmail(
				'akeebabackup_success',
				$site,
				[
					'PROFILE_ID'    => $profile,
					'BACKUPID'      => $backupId,
					'BACKUP_RECORD' => $backupRecordId,
					'ARCHIVE_NAME'  => $archive,
				]
			);
		}
		elseif (!empty($rawData?->Error))
		{
			$this->logger->critical('The backup finished with an error');

			// Log failed backup report
			try
			{
				$report = Reports::fromBackup(
					$site->id,
					$profile,
					false,
					[
						'backupId'     => $backupId,
						'backupRecord' => $backupRecordId,
						'archive'      => $archive,
						'message'      => $rawData?->Error,
					]
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception $e)
			{
				// Whatever
			}

			// Send email
			$this->sendEmail(
				'akeebabackup_fail',
				$site,
				[
					'PROFILE_ID'    => $profile,
					'BACKUPID'      => $backupId,
					'BACKUP_RECORD' => $backupRecordId,
					'ARCHIVE_NAME'  => $archive,
					'MESSAGE'       => $rawData?->Error,
				]
			);

			$this->backupRecordsCacheBuster($site);

			// Die.
			throw new \RuntimeException($rawData?->Error);
		}

		if (!$rawData?->HasRun)
		{
			$this->backupRecordsCacheBuster($site);
		}

		return $rawData?->HasRun ? Status::WILL_RESUME->value : Status::OK->value;
	}

	private function sendEmail(string $type, Site $site, array $vars = []): void
	{
		$vars = array_merge(
			[
				'SITE_NAME' => $site->name,
				'SITE_URL'  => $site->getBaseUrl(),
				'SITE_ID'   => $site->getId(),
			], $vars
		);

		$data = new Registry();
		$data->set('template', $type);
		$data->set('email_variables', $vars);
		$data->set('permissions', ['panopticon.admin', 'panopticon.editown']);

		$queueItem = new QueueItem(
			$data->toString(),
			QueueTypeEnum::MAIL->value,
			$site->getId(),
		);

		$queue = $this->container->queueFactory->makeQueue(QueueTypeEnum::MAIL->value);

		$queue->push($queueItem, 'now');
	}

	private function backupRecordsCacheBuster(Site $site): void
	{
		$from   = 0;
		$limits = [0, 5, 10, 15, 20, 25, 30, 50, 100];
		/** @var \Symfony\Contracts\Cache\CacheInterface $pool */
		$pool = $this->container->cacheFactory->pool('akeebabackup');

		foreach ($limits as $limit)
		{
			$key = sprintf('backupList-%d-%d-%d', $site->id, $from, $limit);

			$pool->delete($key);
		}
	}
}