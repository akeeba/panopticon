<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Setup as SetupModel;
use Awf\Filesystem\Factory as FilesystemFactory;
use Awf\Mvc\Controller;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Exception;
use Throwable;

class Setup extends Controller
{
	use ACLTrait;

	public function execute($task): bool|null
	{
		$this->aclCheck($task);

		if (
			!(defined('AKEEBADEBUG') && AKEEBADEBUG)
			&& @file_exists($this->container->appConfig->getDefaultPath())
			&& !$this->container->userManager->getUser()->getPrivilege('panopticon.super')
			&& !$this->container->segment->get('panopticon.installing', false)
		)
		{
			return false;
		}

		$this->container->application->getDocument()->getMenu()->disableMenu('main');

		return parent::execute($task);
	}

	public function precheck(): void
	{
		// If the session save path is not writable,
		$path = $this->container->session->getSavePath();

		if (!@is_dir($path) || !@is_writeable($path))
		{
			$router = $this->container->router;
			$this->setRedirect($router->route('index.php?view=setup&task=session&layout=session'));

			return;
		}

		$this->container->segment->set('panopticon.installing', true);

		$this->display();
	}

	public function session(): void
	{
		$this->getView()->setLayout('session');

		$this->display();
	}

	public function saveSession(): void
	{
		try
		{
			/** @var SetupModel $model */
			$model = $this->getModel();

			// Apply configuration settings to app config
			$model->setSetupParameters();

			/**
			 * Try to connect to (S)FTP, if something like that was configured in the previous page.
			 *
			 * If it fails we get a nice exception to throw us to the previous page.
			 *
			 * @noinspection PhpUnusedLocalVariableInspection
			 */
			$fs          = FilesystemFactory::getAdapter($this->container, false);
			$sessionPath = $this->container->session->getSavePath();

			$this->container->application->createOrUpdateSessionPath($sessionPath, false);
		}
		catch (Exception $e)
		{
			$errorMessage = base64_encode($e->getMessage());
			$url          = $this->container->router->route('index.php?view=setup&task=session&error=' . $errorMessage);
			$this->setRedirect($url);

			return;
		}

		$url = $this->container->router->route('index.php?view=setup');
		$this->setRedirect($url);
	}

	public function database(): void
	{
		$this->getView()->setLayout('database');

		$this->display();
	}

	public function installDatabase(): void
	{
		try
		{
			/** @var SetupModel $model */
			$model = $this->getModel();

			$model->applyDatabaseParameters();
			$model->installDatabase();

			$this->setRedirect($this->container->router->route('index.php?view=setup&task=setup'));
		}
		catch (Exception $e)
		{
			$this->setRedirect($this->container->router->route('index.php?view=setup&task=database'), $e->getMessage(), 'error');
		}
	}

	public function setup(): void
	{
		$this->getView()->setLayout('setup');

		$this->display();
	}

	public function saveconfig()
	{
		$this->getView()->setLayout('saveconfig');

		try
		{
			/** @var SetupModel $model */
			$model = $this->getModel();

			$model->applyDatabaseParameters();
			$model->setSetupParameters();
			$model->createAdminUser();
		}
		catch (Throwable $e)
		{
			$url = $this->container->router->route('index.php?view=setup&task=setup');
			$this->setRedirect($url, $e->getMessage(), 'error');

			return;
		}

		try
		{
			// Save the configuration
			$this->container->appConfig->saveConfiguration();

			// Redirect to the CRON setup page – we're done here
			$this->setRedirect($this->container->router->route('index.php?view=setup&task=cron'));
		}
		catch (Exception $e)
		{
			// We could not save the configuration. Show the page informing the user of the next steps to follow.
			$this->getView()->setLayout('saveconfig');

			parent::display();
		}
	}

	public function cron()
	{
		$this->getView()->setLayout('cron');

		// TODO Check if test task is registered; if not, register it

		$this->display();
	}
}