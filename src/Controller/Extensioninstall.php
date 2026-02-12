<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Awf\Mvc\Controller;
use Awf\Registry\Registry;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

class Extensioninstall extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function main()
	{
		$siteModel   = $this->getModel('site');
		$mainModel   = $this->getModel('main');
		$groupsModel = $this->getModel('groups');

		$view = $this->getView();
		$view->setModel('site', $siteModel);
		$view->setModel('main', $mainModel);
		$view->setModel('groups', $groupsModel);

		parent::main();
	}

	public function review()
	{
		$this->csrfProtection();

		// Get site IDs from POST
		$siteIdsRaw = $this->input->post->getString('site_ids', '');
		$siteIds    = @json_decode($siteIdsRaw, true);

		if (!is_array($siteIds) || empty($siteIds))
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=extensioninstall'),
				$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_ERR_NO_SITES'),
				'error'
			);

			return;
		}

		// Validate site IDs and permissions
		$user     = $this->container->userManager->getUser();
		$validIds = [];

		foreach ($siteIds as $siteId)
		{
			$siteId = (int) $siteId;

			if ($siteId <= 0)
			{
				continue;
			}

			/** @var Site $site */
			$site = clone $this->getModel('site');

			try
			{
				$site->findOrFail($siteId);
			}
			catch (\Exception)
			{
				continue;
			}

			// Check per-site admin or run privilege
			if (
				!$user->authorise('panopticon.admin', $site)
				&& !$user->authorise('panopticon.run', $site)
			)
			{
				continue;
			}

			$validIds[] = $siteId;
		}

		if (empty($validIds))
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=extensioninstall'),
				$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_ERR_NO_PERMISSION'),
				'error'
			);

			return;
		}

		// Store in session
		$this->container->segment->set('extensioninstall.sites', $validIds);

		// Render the review view
		$siteModel = $this->getModel('site');
		$view      = $this->getView();
		$view->setModel('site', $siteModel);

		$view->setLayout('review');
		$view->task = 'review';
		$view->doTask = 'review';
		$view->display();
	}

	public function install()
	{
		$this->csrfProtection();

		$siteIds = $this->container->segment->get('extensioninstall.sites', []);

		if (empty($siteIds))
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=extensioninstall'),
				$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_ERR_NO_SITES'),
				'error'
			);

			return;
		}

		// Get the URL or file
		$url      = trim($this->input->post->getString('url', ''));
		$filePath = null;

		// Handle file upload
		$file = $this->input->files->get('package_file', null, 'raw');

		if (!empty($file) && !empty($file['name']) && $file['error'] === UPLOAD_ERR_OK)
		{
			$cacheDir = APATH_CACHE . '/extension_install';

			if (!is_dir($cacheDir))
			{
				@mkdir($cacheDir, 0755, true);
			}

			$safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '', basename($file['name']));

			if (empty($safeName))
			{
				$safeName = 'package.zip';
			}

			$filePath = $cacheDir . '/' . $safeName;

			if (file_exists($filePath))
			{
				$filePath .= '-' . bin2hex(random_bytes(4));
			}

			if (!move_uploaded_file($file['tmp_name'], $filePath))
			{
				$this->setRedirect(
					$this->container->router->route('index.php?view=extensioninstall'),
					$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_ERR_NO_URL_OR_FILE'),
					'error'
				);

				return;
			}
		}

		if (empty($url) && empty($filePath))
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=extensioninstall'),
				$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_ERR_NO_URL_OR_FILE'),
				'error'
			);

			return;
		}

		// Create the background task
		$user   = $this->container->userManager->getUser();
		$params = new Registry();
		$params->set('sites', $siteIds);
		$params->set('initiating_user', $user->getId());
		$params->set('run_once', 'disable');

		if (!empty($url))
		{
			$params->set('url', $url);
		}

		if (!empty($filePath))
		{
			$params->set('file', $filePath);
		}

		// Try to reuse an existing disabled extensioninstall task
		/** @var \Akeeba\Panopticon\Model\Task $task */
		$task = $this->container->mvcFactory->makeTempModel('Task');
		$task->setState('type', 'extensioninstall');
		$task->setState('enabled', 0);

		$existing = $task->get(true, 0, 1);

		if ($existing->count() > 0)
		{
			$task = $existing->first();
		}
		else
		{
			$task = $this->container->mvcFactory->makeTempModel('Task');
			$task->site_id = null;
			$task->type    = 'extensioninstall';
		}

		$task->params          = $params->toString();
		$task->storage         = '{}';
		$task->enabled         = 1;
		$task->last_exit_code  = Status::INITIAL_SCHEDULE->value;
		$task->locked          = null;
		$task->priority        = 1;

		// Schedule to run immediately
		$tz   = $this->container->appConfig->get('timezone', 'UTC');
		$then = $this->container->dateFactory('now', $tz);
		$then->add(new \DateInterval('PT2S'));

		$task->cron_expression = $then->minute . ' ' . $then->hour . ' '
			. $then->day . ' ' . $then->month . ' ' . $then->dayofweek;
		$task->last_execution  = (clone $then)->sub(new \DateInterval('PT1M'))->toSql();
		$task->next_execution  = $then->toSql();

		$task->setState('disable_next_execution_recalculation', 1);
		$task->save();

		// Clear session
		$this->container->segment->set('extensioninstall.sites', null);

		$this->setRedirect(
			$this->container->router->route('index.php?view=main'),
			$this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_TASK_CREATED'),
			'success'
		);
	}
}
