<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\View\JsonApiView;
use Joomla\CMS\Updater\Updater;
use Joomla\Component\Installer\Administrator\Model\UpdateModel;

class UpdatesController extends ApiController
{
	protected $contentType = 'updates';

	protected $default_view = 'updates';

	public function refresh()
	{
		// Load com_installer language and model
		$this->app->getLanguage()
			->load('com_installer', JPATH_ADMINISTRATOR);

		/** @var UpdateModel $model */
		$model = $this->getComInstallerFactory()
			->createModel('update', 'Administrator');

		// Get the updates caching duration.
		$params       = ComponentHelper::getComponent('com_installer')->getParams();
		$cacheTimeout = 3600 * ((int) $params->get('cachetimeout', 6));

		// Get the minimum stability.
		$minimumStability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);

		// Purge the table before checking again?
		$force = $this->input->getInt('force', 0);

		if ($force === 1)
		{
			$model->purge();
		}

		$model->findUpdates(0, $cacheTimeout, $minimumStability);

		$this->app->setHeader('status', 204);
	}

	public function update()
	{
		// Make sure the user has administrator privileges on com_installer
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.manage', 'com_installer'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Load com_installer language
		$this->app->getLanguage()
			->load('com_installer', JPATH_ADMINISTRATOR);

		// Get the cache timeout and minimum stability.
		$params           = ComponentHelper::getComponent('com_installer')->getParams();
		$minimumStability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);
		$cacheTimeout     = 3600 * ((int) $params->get('cachetimeout', 6));

		// Get the extension IDs to update
		$extensionIDs = (array) $this->input->get('eid', [], 'int');
		$extensionIDs = array_filter($extensionIDs);

		// Fail on empty array
		if (empty($extensionIDs))
		{
			$this->app->getDocument()->setErrors([
				[
					'title' => $this->app->getLanguage()->_('No extensions to update'),
					'code'  => 400,
				],
			]);

			$this->app->setHeader('status', 400);

			return;
		}

		$reportedResults = [];

		foreach ($extensionIDs as $eid)
		{
			/**
			 * IMPORTANT! Always clear the Installer cache and create a new UpdateModel instance.
			 *
			 * There is a bug in Joomla when updating heterogeneous extension types, e.g. packages, modules, and plugins
			 * with the same UpdateModel instance. The UpdateModel gets a static Installer instance which caches the
			 * adapter type the first time it's used.
			 *
			 * If a subsequent update is for a different extension type the WRONG installer adapter is used, causing the
			 * pre-/post-installation scripts to not run, or throw PHP type errors.
			 *
			 * By resetting the Installer adapter and creating a fresh UpdateModel instance we work around this bug.
			 *
			 * PS: For anyone complaining that I should instead report it to the Joomla project, so it can be fixed
			 *     for everyone: I have, since October 2022: https://github.com/joomla/joomla-cms/issues/38956
			 *     I am not the only one hitting this problem, see https://github.com/joomla/joomla-cms/issues/39148
			 *     So, um, thank you for being condescending; you're part of the problem.
			 */
			$refClass = new \ReflectionClass(Installer::class);
			$refProp  = $refClass->getProperty('instances');
			$refProp->setAccessible(true);
			$refProp->setValue([]);

			/** @var UpdateModel $model */
			$model = $this->getComInstallerFactory()
				->createModel('update', 'Administrator', ['ignore_request' => true]);

			// Get the updates for the extension
			$model->setState('filter.extension_id', $eid);
			$updates = $model->getItems();

			if (empty($updates))
			{
				$this->app->enqueueMessage('No updates', CMSApplication::MSG_WARNING);

				$reportedResults[$eid] = (object) [
					'id'       => $eid,
					'status'   => false,
					'messages' => $this->app->getMessageQueue(true),
				];

				continue;
			}

			$update = array_pop($updates);

			$model->update([$update->update_id], $minimumStability);

			$reportedResults[$eid] = (object) [
				'id'       => $eid,
				'status'   => $model->getState('result'),
				'messages' => $this->app->getMessageQueue(true),
			];
		}

		// Finally, communicate the reported results
		$viewType   = $this->app->getDocument()->getType();
		$viewName   = $this->input->get('view', $this->default_view);
		$viewLayout = $this->input->get('layout', 'default', 'string');

		try
		{
			/** @var JsonApiView $view */
			$view = $this->getView(
				$viewName,
				$viewType,
				'',
				['base_path' => $this->basePath, 'layout' => $viewLayout, 'contentType' => $this->contentType]
			);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException($e->getMessage());
		}

		$view->document = $this->app->getDocument();
		$view->displayList($reportedResults);
	}

	private function getComInstallerFactory(): MVCFactory
	{
		return $this->app->bootComponent('com_installer')->getMVCFactory();
	}
}