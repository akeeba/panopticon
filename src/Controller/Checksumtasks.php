<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Container\Container;
use Awf\Mvc\DataController;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

class Checksumtasks extends DataController
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
		if (!empty($this->redirect))
		{
			$uri = new Uri($this->redirect);

			if (in_array(strtolower($uri->getVar('view')), ['checksumtasks', 'checksumtask']))
			{
				$uri->setVar('site_id', $this->site->getId());

				$this->redirect = $uri->toString();
			}
		}

		return parent::redirect();
	}

	public function execute($task)
	{
		$this->aclCheck($task);

		$siteId     = $this->input->getInt('site_id', null);
		$this->site = $this->getModel('Site');

		try
		{
			$this->site->findOrFail($siteId);
		}
		catch (\Exception)
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

	protected function onBeforeEdit()
	{
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if ($model->getParams()->get('enqueued_checksums'))
		{
			$this->setRedirect(
				$this->container->router->route(
					sprintf(
						'index.php?view=checksumtasks&site_id=%d',
						$this->site->getId()
					)
				),
				$this->getLanguage()->text('PANOPTICON_CHECKSUMTASKS_ERR_CANNOT_EDIT_MANUAL'),
				'error'
			);

			$this->redirect();
		}

		return true;
	}

	public function getModel($name = null, $config = [])
	{
		$model = parent::getModel($name, $config);

		if ($model instanceof Task)
		{
			$model->setState('site_id', $this->site->id);
			$model->where('site_id', '=', $this->site->id);

			$model->setState('type', 'corechecksums');
			$model->where('type', '=', 'corechecksums');

			$db = $model->getDbo();

			$fltManual = $model->getState('manual');

			if (is_numeric($fltManual) && $fltManual)
			{
				$model->whereRaw(
					"JSON_EXTRACT(" . $db->quoteName('params') . ', ' . $db->quote('$.enqueued_checksums') . ') = 1'
				);
			}
			elseif (is_numeric($fltManual) && !$fltManual)
			{
				$model->whereRaw(
					"NOT JSON_CONTAINS_PATH(" . $db->quoteName('params') . ', ' . $db->quote('one') . ', ' . $db->quote('$.enqueued_checksums') . ')'
				);
			}
		}

		return $model;
	}

	public function getView($name = null, $config = [])
	{
		$view = parent::getView($name, $config);

		$view->site = $this->site;

		return $view;
	}

	protected function onBeforeApplySave(array &$data): void
	{
		$params         = $data['params'] ?? [];
		$data['params'] = json_encode($params);

		$cron = $data['cron'];
		unset($data['cron']);

		$data['cron_expression'] =
			($cron['minutes'] ?? '*') . ' ' .
			($cron['hours'] ?? '*') . ' ' .
			($cron['dom'] ?? '*') . ' ' .
			($cron['month'] ?? '*') . ' ' .
			($cron['dow'] ?? '*');

		$data['site_id'] = $this->site->getId();
		$data['type']    = 'corechecksums';
	}
}
