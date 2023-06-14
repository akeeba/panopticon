<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;


use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Awf\Mvc\Model;
use Awf\Registry\Registry;

defined('AKEEBA') || die;

#[AsTask(
	name: 'akeebabackup',
	description: 'PANOPTICON_TASKTYPE_AKEEBABACKUP'
)]
class AkeebaBackup extends \Akeeba\Panopticon\Library\Task\AbstractCallback
{

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @var Site $site */
		$site = Model::getTmpInstance(null, 'Site', $this->container);
		$site->findOrFail($task->site_id);

		// Add a site-specific logger
		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Load the task configuration parameters
		$params      = $task->params instanceof Registry ? $task->params : new Registry($task->params);
		$profile     = $params->get('profile_id', 1);
		$description = $params->get('description', 'Remote backup taken on [DATE] [TIME]');
		$comment     = $params->get('comment', '');

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

			// Send email
			$this->sendEmail(
				'akeebabackup_fail',
				$site,
				[
					'[PROFILE_ID]'    => $profile,
					'[BACKUPID]'      => $backupId,
					'[BACKUP_RECORD]' => $backupRecordId,
					'[ARCHIVE_NAME]'  => $archive,
					'[MESSAGE]'       => $errorMessage,
				]
			);

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

			// Send email
			$this->sendEmail(
				'akeebabackup_success',
				$site,
				[
					'[PROFILE_ID]'    => $profile,
					'[BACKUPID]'      => $backupId,
					'[BACKUP_RECORD]' => $backupRecordId,
					'[ARCHIVE_NAME]'  => $archive,
				]
			);
		}
		elseif (!empty($rawData?->Error))
		{
			$this->logger->critical('The backup finished with an error');

			// Send email
			$this->sendEmail(
				'akeebabackup_fail',
				$site,
				[
					'[PROFILE_ID]'    => $profile,
					'[BACKUPID]'      => $backupId,
					'[BACKUP_RECORD]' => $backupRecordId,
					'[ARCHIVE_NAME]'  => $archive,
					'[MESSAGE]'       => $rawData?->Error,
				]
			);

			// Die.
			throw new \RuntimeException($rawData?->Error);
		}

		return $rawData?->HasRun ? Status::WILL_RESUME->value : Status::OK->value;
	}

	private function sendEmail(string $type, Site $site, array $vars = []): void
	{
		$vars = array_merge(
			[
				'[SITE_NAME]' => $site->getName(),
				'[SITE_ID]'   => $site->getId(),
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
}