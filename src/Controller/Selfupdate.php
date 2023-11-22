<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Awf\Mvc\Controller;

class Selfupdate extends Controller
{
	use ACLTrait;
	use TasksPausedTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function onBeforeDefault(): bool
	{
		if ($this->getTasksPausedFlag())
		{
			$this->setTasksPausedFlag(false);
		}

		$force = $this->input->getInt('force', false);

		if ($force)
		{
			/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
			$model = $this->getModel();
			$model->bustCache();
			$model->getUpdateInformation(true);

			$this->setRedirect($this->getContainer()->router->route('index.php?view=selfupdate'));

			$this->redirect();
		}

		$this->getView()->force = $force;

		return true;
	}

	public function preupdate()
	{
		if (!$this->getTasksPausedFlag())
		{
			$this->setTasksPausedFlag(true);
		}

		if (!$this->areTasksRunning())
		{
			$url = $this->container->router->route('index.php?view=selfupdate&task=update');

			$this->setRedirect($url);
		}

		$this->getView()->setLayout('preupdate');
		$this->display();
	}

	public function update()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$targetFile = $model->download();
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				$this->getLanguage()->text('PANOPTICON_SELFUPDATE_ERR_DOWNLOADFAILED') . ' ' . $e->getMessage()
			);
		}

		$this->container->segment->setFlash('selfupdate.localfile', $targetFile);

		$url = $this->container->router->route('index.php?view=selfupdate&task=install');

		$this->setRedirect($url);
	}

	public function install()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$sourceFile = $this->container->segment->getFlash('selfupdate.localfile');

			$didExtract = $model->extract($sourceFile);
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				$this->getLanguage()->text('PANOPTICON_SELFUPDATE_ERR_EXTRACTFAILED') . ' ' . $e->getMessage()
			);
		}

		$url = $this->container->router->route('index.php?view=selfupdate&task=postinstall');

		$this->setRedirect($url);
	}

	public function postinstall()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$model->postUpdate();

			$this->setTasksPausedFlag(false);
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				$this->getLanguage()->text('PANOPTICON_SELFUPDATE_ERR_POSTINSTALLFAILED') . ' ' . $e->getMessage()
			);
		}

		$url = $this->container->router->route(
			'index.php?view=selfupdate', $this->getLanguage()->text('PANOPTICON_SELFUPDATE_LBL_SUCCESS')
		);

		$this->setRedirect($url);
	}
}