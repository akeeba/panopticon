<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application;
use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Main as MainModel;
use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Document\Json;
use Awf\Mvc\Controller;

class Main extends Controller
{
	use ACLTrait;

	public function __construct(Container $container = null)
	{
		$this->modelName = 'site';

		parent::__construct($container);
	}

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function heartbeat()
	{
		/** @var MainModel $model */
		$model             = $this->getModel('Main');
		$lastExecutionTime = $model->getLastCronExecutionTime();

		if (empty($lastExecutionTime))
		{
			$isValid = false;
		}
		else
		{
			$lastPlausibleRun = (new Date())->sub(new \DateInterval('PT55S'));
			$lastPlausibleRun->setTime($lastPlausibleRun->hour, $lastPlausibleRun->minute, 0, 0);

			$isValid = $lastPlausibleRun->diff($lastExecutionTime)->invert === 0;
		}

		/**
		 * @var Application $app
		 * @var Json        $document
		 */
		$app      = $this->container->application;
		$document = $app->getDocument();

		$document->setUseHashes(false);
		$document->setBuffer(json_encode($isValid));
		$app->render();
		$app->close();
	}

	public function onBeforeDefault(): bool
	{
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		// If the current user has the Super privilege we're going to do some housekeeping
		if ($this->container->userManager->getUser()->getPrivilege('panopticon.super'))
		{
			/** @var \Akeeba\Panopticon\Model\Setup $model */
			$model = $this->getModel('Setup');
			// Check the installed default tasks
			$model->checkDefaultTasks();
			// Make sure the DB tables are installed correctly
			$model->installDatabase();
		}

		// Pass the Selfupdate model to the view
		$selfUpdateModel = $this->getModel('selfupdate');
		$this->getView()->setModel('selfupdate', $selfUpdateModel);

		return true;
	}
}