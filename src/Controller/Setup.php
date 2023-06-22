<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Setup as SetupModel;
use Awf\Filesystem\Factory as FilesystemFactory;
use Awf\Mvc\Controller;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use Exception;
use Throwable;

class Setup extends Controller
{
	use ACLTrait;

	public function execute($task): bool|null
	{
		$this->aclCheck($task);

		if ($this->needsRedirectToCronTask() || $this->needsRedirectToMainView())
		{
			return true;
		}

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
			$this->container->appConfig->set('live_site', Uri::base());
			$this->container->appConfig->saveConfiguration();

			if (function_exists('opcache_invalidate'))
			{
				opcache_invalidate(APATH_ROOT . '/config.php', true);
			}

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

		/** @var SetupModel $model */
		$model = $this->getModel();

		$model->conditionallyCreateWebCronKey();
		$model->reRegisterMaxExecTask();

		$document = $this->container->application->getDocument();

		Template::addJs('media://js/setup.js', defer: true);
		$document->addScriptOptions('panopticon.benchmark', [
			'url'      => $this->container->router->route('index.php?view=setup&task=cronHeartbeat'),
			'nextPage' => $this->container->router->route('index.php?view=setup&task=finish'),
			'token'    => $this->container->session->getCsrfToken()->getValue(),
		]);

		Text::script('PANOPTICON_SETUP_CRON_ERR_NO_MAXEXEC_TASK');
		Text::script('PANOPTICON_SETUP_CRON_ERR_XHR_ABORT');
		Text::script('PANOPTICON_SETUP_CRON_ERR_XHR_TIMEOUT');
		Text::script('PANOPTICON_SETUP_CRON_ERR_AJAX_HEAD');
		Text::script('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_STATUS');
		Text::script('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_INTERNAL');
		Text::script('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_READYSTATE');
		Text::script('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_RAW');

		$this->display();
	}

	public function cronHeartbeat(): void
	{
		/** @var SetupModel $model */
		$model           = $this->getModel();
		$heartbeatResult = $model->getHeartbeat();

		@ob_end_clean();
		header('Content-type: application/json');
		header('Connection: close');
		// DO NOT INLINE. We want to isolate any PHP notice / warnings output from the JSON output.
		echo json_encode($heartbeatResult);
		flush();

		$this->container->application->close();
	}

	public function skipcron()
	{
		/** @var SetupModel $model */
		$model = $this->getModel();

		$model->removeMaxExecTask();

		$appConfig = $this->container->appConfig;
		$appConfig->set('finished_setup', true);
		$appConfig->set('max_execution', 60);
		$appConfig->set('cron_stuck_threshold', 3);
		$appConfig->set('execution_bias', 75);
		$this->container->appConfig->saveConfiguration();

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate(APATH_ROOT . '/config.php', true);
		}

		$model->installDefaultTasks();

		$this->setRedirect(
			$this->container->router->route('index.php?view=main')
		);

		return true;
	}

	public function finish()
	{
		$this->csrfProtection();

		/** @var SetupModel $model */
		$model = $this->getModel();

		$model->removeMaxExecTask();

		$maxExec    = min(
			180,
			intval(
				ceil(
					$this->input->getInteger('maxexec', 0) / 5
				) * 5
			)
		);
		$maxMinutes = max(3, intval(ceil($maxExec / 60)) + 1);

		$appConfig = $this->container->appConfig;
		$appConfig->set('finished_setup', true);
		$appConfig->set('max_execution', $maxExec);
		$appConfig->set('cron_stuck_threshold', $maxMinutes);
		$appConfig->set('execution_bias', 75);

		$appConfig->saveConfiguration();

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate(APATH_ROOT . '/config.php', true);
		}

		$model->installDefaultTasks();

		$this->getView()->setLayout('finish');

		$this->getView()->maxExec = $maxExec;

		$this->display();
	}

	private function needsRedirectToMainView(): bool
	{
		if (!$this->container->appConfig->get('finished_setup', false))
		{
			return false;
		}

		$this->setRedirect(
			$this->container->router->route('index.php?view=main')
		);

		return true;
	}

	private function needsRedirectToCronTask(): bool
	{
		$isDebug          = defined('AKEEBADEBUG') && AKEEBADEBUG;
		$hasConfigFile    = @file_exists($this->container->appConfig->getDefaultPath());
		$isLoggedIn       = $this->container->userManager->getUser()->getPrivilege('panopticon.super');
		$isInstalling     = $this->container->segment->get('panopticon.installing', false);
		$hasFinishedSetup = (bool) $this->container->appConfig->get('finished_setup', false);
		$allowedTask      = in_array(
			$this->container->input->getCmd('task', 'main'),
			['cron', 'cronHeartbeat', 'skipcron', 'finish']
		);

		if (
			((!$isDebug && $hasConfigFile) || ($isLoggedIn && !$isInstalling))
			&& !$hasFinishedSetup
			&& !$allowedTask
		)
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=setup&task=cron')
			);

			return true;
		}

		return false;
	}

}