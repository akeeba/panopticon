<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Model\Site;
use Awf\Mvc\DataController;
use Awf\Uri\Uri;

class Corechecksums extends DataController
{
	use ACLTrait;

	private Site $site;

	public function redirect(): bool
	{
		if (!empty($this->redirect))
		{
			$uri = new Uri($this->redirect);

			if (strtolower($uri->getVar('view')) === 'corechecksums')
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
			&& !$user->authorise('panopticon.view', $this->site)
			&& !$user->authorise('panopticon.editown', $this->site)
		)
		{
			throw new AccessDenied();
		}

		return parent::execute($task);
	}

	public function getView($name = null, $config = [])
	{
		$view = parent::getView($name, $config);

		$view->site = $this->site;

		return $view;
	}

	public function getModel($name = null, $config = [])
	{
		if (!empty($name))
		{
			$modelName = strtolower($name);
		}
		elseif (!empty($this->modelName))
		{
			$modelName = strtolower($this->modelName);
		}
		else
		{
			$modelName = strtolower($this->view);
		}

		if (!array_key_exists($modelName, $this->modelInstances))
		{
			if (empty($config))
			{
				$config = $this->config;
			}

			if (empty($name))
			{
				$config['modelTemporaryInstance'] = true;
			}
			else
			{
				$config['modelTemporaryInstance'] = false;
				$config['modelClearState']        = true;
				$config['modelClearInput']        = true;
			}

			$this->container['mvc_config'] = $config;

			$this->modelInstances[$modelName] = $this->container->mvcFactory->makeModel($modelName);
		}

		return $this->modelInstances[$modelName];
	}
}
