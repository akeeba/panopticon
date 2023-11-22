<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Task;
use Awf\Mvc\DataController;

class Tasks extends DataController
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	protected function onBeforeRemove()
	{
		/** @var Task $model */
		$model = $this->getModel();

		$ids = $this->getIDsFromRequest($model, false);

		foreach ($ids as $id)
		{
			$model->findOrFail($id);

			if ($model->site_id <= 0)
			{
				if ($customURL = $this->input->getBase64('returnurl', ''))
				{
					$customURL = base64_decode($customURL);
				}

				$router = $this->container->router;
				$url = !empty($customURL) ? $customURL : $router->route('index.php?view=tasks');

				$this->setRedirect($url, $this->getLanguage()->text('PANOPTICON_TASKS_ERR_NO_DELETE_SYSTEM'), 'error');

				$this->redirect();
			}
		}

		return parent::onBeforeRemove();
	}

	protected function onBeforeUnpublish()
	{
		/** @var Task $model */
		$model = $this->getModel();

		$ids = $this->getIDsFromRequest($model, false);

		foreach ($ids as $id)
		{
			$model->findOrFail($id);

			if ($model->site_id <= 0)
			{
				if ($customURL = $this->input->getBase64('returnurl', ''))
				{
					$customURL = base64_decode($customURL);
				}

				$router = $this->container->router;
				$url = !empty($customURL) ? $customURL : $router->route('index.php?view=tasks');

				$this->setRedirect($url, $this->getLanguage()->text('PANOPTICON_TASKS_ERR_NO_DISABLE_SYSTEM'), 'error');

				$this->redirect();
			}
		}

		return parent::onBeforeUnpublish();
	}


}