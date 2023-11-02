<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;
use Awf\Text\Text;

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
				? Text::sprintf('PANOPTICON_DBTOOLS_LBL_DELETED', $escapedFile)
				: Text::sprintf('PANOPTICON_DBTOOLS_LBL_NOT_DELETED', $escapedFile),
			$success ? 'success' : 'error'
		);
	}
}