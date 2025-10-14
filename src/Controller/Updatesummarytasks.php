<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Container\Container;
use Awf\Mvc\DataController;
use Awf\Uri\Uri;

class Updatesummarytasks extends DataController
{
	use ACLTrait;

	private Site $site;

	public function __construct(?Container $container = null)
	{
		$this->modelName = 'Task';

		parent::__construct($container);
	}

	public function redirect(): bool
	{
		// Magically add the site_id to the redirection URLs as needed.
		if (!empty($this->redirect))
		{
			$uri = new Uri($this->redirect);

			if (in_array(strtolower($uri->getVar('view')), ['updatesummarytasks', 'updatesummarytask']))
			{
				$uri->setVar('site_id', $this->site->getId());

				$this->redirect = $uri->toString();
			}
		}

		return parent::redirect();
	}

	public function execute($task)
	{
		// First, do the standard ACL check
		$this->aclCheck($task);

		// In all cases we need a site_id in the request and ensure the current user has admin privileges on the site
		$siteId = $this->input->getInt('site_id', null);
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->site = $this->getModel('Site');

		try
		{
			$this->site->findOrFail($siteId);
		}
		catch (\Exception $e)
		{
			throw new AccessDenied();
		}

		$user = $this->getContainer()->userManager->getUser();

		if (
			!$user->authorise('panopticon.admin', $this->site)
			&& !$user->authorise('panopticon.editown', $this->site)
		)
		{
			throw new AccessDenied();
		}

		return parent::execute($task);
	}

	public function getModel($name = null, $config = [])
	{
		$model = parent::getModel($name, $config);

		// Forcibly filter the task model by site_id and type
		if ($model instanceof Task)
		{
			$model->setState('site_id', $this->site->id);
			$model->where('site_id', '=', $this->site->id);

			$model->setState('type', 'updatesummaryemail');
			$model->where('type', '=', 'updatesummaryemail');
		}

		return $model;
	}

	public function getView($name = null, $config = [])
	{
		$view = parent::getView($name, $config);

		// Pass the site object to the view
		$view->site = $this->site;

		return $view;
	}

	protected function onBeforeApplySave(array &$data): void
	{
		$data['core_updates']       = $data['core_updates'] ?? 0;
		$data['extension_updates']  = $data['extension_updates'] ?? 0;
		$data['prevent_duplicates'] = $data['prevent_duplicates'] ?? 0;
		$data['enabled']            = $data['enabled'] ?? 0;

		// Construct the JSON params from $data['params']
		$params                       = $data['params'] ?: [];
		$params['core_updates']       = boolval($data['core_updates']);
		$params['extension_updates']  = boolval($data['extension_updates']);
		$params['prevent_duplicates'] = boolval($data['prevent_duplicates']);
		$data['params']               = json_encode($params);

		unset($data['core_updates']);
		unset($data['extension_updates']);
		unset($data['prevent_duplicates']);

		// Construct the cron_expression from $data['cron']
		$cron = $data['cron'];
		unset($data['cron']);

		$data['cron_expression'] =
			($cron['minutes'] ?? '*') . ' ' .
			($cron['hours'] ?? '*') . ' ' .
			($cron['dom'] ?? '*') . ' ' .
			($cron['month'] ?? '*') . ' ' .
			($cron['dow'] ?? '*');

		// Force the site_id and type
		$data['site_id'] = $this->site->getId();
		$data['type']    = 'updatesummaryemail';
	}
}