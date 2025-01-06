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

/**
 * Site Action Summary Tasks
 *
 * @since 1.1.0
 */
class Actionsummarytasks extends DataController
{
	use ACLTrait;

	private Site $site;

	public function __construct(Container $container = null)
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

			if (in_array(strtolower($uri->getVar('view')), ['actionsummarytasks', 'actionsummarytask']))
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

			$model->setState('type', 'actionsummaryemail');
			$model->where('type', '=', 'actionsummaryemail');
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
		// Construct the JSON params from $data['params']
		$data['enabled'] ??= 0;
		$data['period']  ??= 'daily';

		$params           = $data['params'] ?: [];
		$params['period'] = $data['period'];
		$data['params']   = json_encode($params);

		unset($data['period']);

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
		$data['type']    = 'actionsummaryemail';
	}
}