<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Model\Site;
use Awf\Mvc\DataController;
use Awf\Mvc\Model;
use Awf\Uri\Uri;

class Overrides extends DataController
{
	use ACLTrait;

	private Site $site;

	public function redirect(): bool
	{
		// Magically add the site_id to the redirection URLs as needed.
		if (!empty($this->redirect))
		{
			$uri = new Uri($this->redirect);

			if (in_array(strtolower($uri->getVar('view')), ['overrides', 'override']))
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

		return parent::execute($task);
	}

	public function getView($name = null, $config = [])
	{
		$view = parent::getView($name, $config);

		// Pass the site object to the view
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
			$appName = $this->container->application->getName();

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
				// Other classes are loaded with persistent state disabled and their state/input blanked out
				$config['modelTemporaryInstance'] = false;
				$config['modelClearState'] = true;
				$config['modelClearInput'] = true;
			}

			$this->container['mvc_config'] = $config;

			$this->modelInstances[$modelName] = $this->container->mvcFactory->makeModel($modelName);
		}

		return $this->modelInstances[$modelName];
	}


}