<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\DBUtils\Export;
use Awf\Mvc\Controller;
use Awf\Timer\Timer;

class Dbtools extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function download()
	{
		$this->csrfProtection();

		/** @var \Akeeba\Panopticon\Model\Dbtools $model */
		$model = $this->getModel();

		$model->downloadFile($this->input->getPath('file', null) ?? '');
	}

	public function delete()
	{
		$this->csrfProtection();

		/** @var \Akeeba\Panopticon\Model\Dbtools $model */
		$model = $this->getModel();

		$file    = $this->input->getPath('file', null) ?? '';
		$success = $model->deleteFile($file);

		$escapedFile = htmlentities($file);

		$this->setRedirect(
			$this->getContainer()->router->route('index.php?view=dbtools'),
			$success
				? $this->getLanguage()->sprintf('PANOPTICON_DBTOOLS_LBL_DELETED', $escapedFile)
				: $this->getLanguage()->sprintf('PANOPTICON_DBTOOLS_LBL_NOT_DELETED', $escapedFile),
			$success ? 'success' : 'error'
		);
	}

	public function startBackup()
	{
		$this->csrfProtection();

		$fileName = sprintf("%s/db_backups/backup-%s.sql", APATH_CACHE, date('Y-m-d-His'));
		$export   = new Export($fileName, $this->getContainer()->db);
		$export->setLogger($this->getContainer()->logger);

		$session = $this->getContainer()->segment;
		$session->set('dbtools.backup.export', json_encode($export));

		$this->display();

		return true;
	}

	public function backup()
	{
		$this->csrfProtection();

		$returnUrl = $this->getContainer()->router->route('index.php?view=dbtools');

		$session = $this->getContainer()->segment;
		$json = $session->get('dbtools.backup.export', null);

		if (empty($json))
		{
			$this->setRedirect($returnUrl, $this->getLanguage()->text('PANOPTICON_DBTOOLS_ERR_BACKUP_INVALID_STATE'), 'error');

			return;
		}

		try
		{
			$export = Export::fromJson($json);
			$export->setLogger($this->getContainer()->logger);
		}
		catch (\JsonException $e)
		{
			$this->setRedirect($returnUrl, $this->getLanguage()->sprintf('PANOPTICON_DBTOOLS_ERR_BACKUP_JSON', $e->getMessage()), 'error');

			return;
		}

		$timer = new Timer(5, 75);

		while ($timer->getTimeLeft())
		{
			$lastStatus = $export->execute();

			if (!$lastStatus)
			{
				break;
			}
		}

		if ($lastStatus === false)
		{
			$this->setRedirect($returnUrl, $this->getLanguage()->text('PANOPTICON_DBTOOLS_LBL_BACKUP_DONE'), 'success');

			return;
		}

		$this->display();
	}
}