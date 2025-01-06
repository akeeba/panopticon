<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\DBUtils\Export;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;
use Awf\Timer\Timer;

#[AsTask(name: 'databasebackup', description: 'PANOPTICON_TASKTYPE_DATABASEBACKUP')]
class DatabaseBackup extends AbstractCallback
{
	public function __invoke(object $task, Registry $storage): int
	{
		if (!$this->container->appConfig->get('dbbackup_auto', true))
		{
			$this->logger->info('Automatic database backups have been disabled; I am NOT taking a backup');

			return Status::OK->value;
		}

		$json           = $storage->get('frozenState', null);

		if (empty($json))
		{
			$this->logger->info('Starting a new automatic database backup');
		}
		else
		{
			$this->logger->info('Continuing the automatic database backup');
		}

		$outputFilename = sprintf("%s/db_backups/backup-%s.sql", APATH_CACHE, date('Y-m-d-His'));
		$db             = $this->container->db;
		$backupObject   = empty($json) ? new Export($outputFilename, $db) : Export::fromJson($json, $db);

		$backupObject->setLogger($this->logger);

		$timer = new Timer(5, 75);

		while ($timer->getTimeLeft())
		{
			if (!$backupObject->execute())
			{
				$this->logger->info('Finished taking an automatic database backup');

				$storage->set('frozenState', null);

				return Status::OK->value;
			}

			$storage->set('frozenState', json_encode($backupObject));
		}

		return Status::WILL_RESUME->value;
	}
}