<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\DBUtils\Export;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

#[AsTask(name: 'databasebackup', description: 'PANOPTICON_TASKTYPE_DATABASEBACKUP')]
class DatabaseBackup extends AbstractCallback
{
	public function __invoke(object $task, Registry $storage): int
	{
		$json           = $storage->get('frozenState', null);
		$outputFilename = sprintf("%s/db_backups/backup-%s.sql", APATH_CACHE, date('Y-m-d-His'));
		$db             = $this->container->db;
		$backupObject   = empty($json) ? new Export($outputFilename, $db) : Export::fromJson($json, $db);

		$backupObject->setLogger($this->logger);

		if ($backupObject->execute())
		{
			$storage->set('frozenState', json_encode($backupObject));

			return Status::WILL_RESUME->value;
		}

		return Status::OK->value;
	}
}