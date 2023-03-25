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

class Setup extends Controller
{
	use ACLTrait;

	public function execute($task): bool|null
	{
		$this->aclCheck($task);

		if (@file_exists($this->container->appConfig->getDefaultPath()))
		{
			return false;
		}

		$this->container->application->getDocument()->getMenu()->disableMenu('main');

		return parent::execute($task);
	}

	public function main(): void
	{
		// If the session save path is not writable,
		$path = $this->container->session->getSavePath();

		if (!@is_dir($path) || !@is_writeable($path))
		{
			$router = $this->container->router;
			$this->setRedirect($router->route('index.php?view=setup&task=session'));

			return;
		}

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
		catch (\Exception $e)
		{
			$errorMessage = base64_encode($e->getMessage());
			$url          = Uri::rebase('?view=setup&task=session&error=' . $errorMessage, $this->container);
			$this->setRedirect($url);

			return;
		}

		$url = Uri::rebase('?view=setup', $this->container);
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

			$this->setRedirect(Uri::rebase('?view=setup&task=setup', $this->container));
		}
		catch (\Exception $e)
		{
			$this->setRedirect(Uri::rebase('?view=setup&task=database', $this->container), $e->getMessage(), 'error');
		}
	}

	public function setup(): void
	{
		$this->getView()->setLayout('setup');

		$this->display();
	}

	public function finish()
	{
		try
		{
			/** @var SetupModel $model */
			$model = $this->getModel();

			// Apply database settings to app config
			$model->applyDatabaseParameters();

			// Apply configuration settings to app config
			$model->setSetupParameters();

			/**
			 * Try to connect to (S)FTP, if something like that was configured in the previous page.
			 *
			 * If it fails we get a nice exception to throw us to the previous page.
			 *
			 * @noinspection PhpUnusedLocalVariableInspection
			 */
			$fs = FilesystemFactory::getAdapter($this->container, false);

			// Try to create the new admin user and log them in
			$model->createAdminUser();
		}
		catch (\Exception $e)
		{
			$url = Uri::rebase('?view=setup&task=setup', $this->container);
			$this->setRedirect($url, $e->getMessage(), 'error');

			return;
		}

		try
		{
			// Save the configuration
			$this->container->appConfig->saveConfiguration();

			// Redirect to the Wizard page – we're done here
			$this->setRedirect(Uri::rebase('?view=wizard', $this->container), Text::_('PANOPTICON_SETUP_MSG_DONE'), 'info');
		}
		catch (\Exception $e)
		{
			// We could not save the configuration. Show the page informing the user of the next steps to follow.
			$this->getView()->setLayout('finish');

			parent::display();
		}

		return;
	}
}