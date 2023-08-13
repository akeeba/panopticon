<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Controller\Trait\AdminToolsIntegrationTrait;
use Akeeba\Panopticon\Controller\Trait\AkeebaBackupIntegrationTrait;
use Akeeba\Panopticon\Exception\SiteConnectionException;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Site as SiteModel;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\EnqueueJoomlaUpdateTrait;
use Akeeba\Panopticon\Task\RefreshSiteInfo;
use Awf\Date\Date;
use Awf\Mvc\DataController;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use stdClass;
use Throwable;

class Sites extends DataController
{
	use ACLTrait;
	use EnqueueJoomlaUpdateTrait;
	use EnqueueExtensionUpdateTrait;
	use AkeebaBackupIntegrationTrait;
	use AdminToolsIntegrationTrait;

	private const CHECKBOX_KEYS = [
		'config.core_update.email_error',
		'config.core_update.email_after',
	];

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function fixJoomlaCoreUpdateSite(): void
	{
		$this->csrfProtection();

		$id = $this->input->get->getInt('id', 0);
		/** @var SiteModel $site */
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		try
		{
			$site->findOrFail($id);

			$site->fixCoreUpdateSite();

			$this->refreshSiteInformation();

			return;
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_ERR_COREUPDATESITEFIX_FAILED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function refreshSiteInformation(): void
	{
		$this->csrfProtection();

		$id = $this->input->get->getInt('id', 0);
		/** @var Site $site */
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		try
		{
			$site->findOrFail($id);

			$this->doRefreshSiteInformation($site);

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_REFRESHED_OK');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_ERR_REFRESHED_FAILED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function refreshExtensionsInformation(): void
	{
		$this->csrfProtection();

		$id = $this->input->get->getInt('id', 0);
		/** @var Site $site */
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		try
		{
			$site->findOrFail($id);

			$this->doRefreshExtensionsInformation($site);

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_EXTENSIONS_REFRESHED_OK');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_ERR_EXTENSIONS_REFRESHED_FAILED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function scheduleJoomlaUpdate()
	{
		$this->csrfProtection();

		$id    = $this->input->get->getInt('id', 0);
		$force = $this->input->get->getBool('force', false);

		/** @var SiteModel $site */
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		$site->findOrFail($id);

		try
		{
			/** @noinspection PhpParamsInspection */
			$this->enqueueJoomlaUpdate($site, $this->container, $force);

			// Update the core.lastAutoUpdateVersion after enqueueing
			$site->findOrFail($id);
			$config = new Registry($site->config);
			$config->set('core.lastAutoUpdateVersion', $config->get('core.current.version'));
			$site->config = $config->toString();
			$site->save();

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_OK');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_ERR_JUPDATE_SCHEDULE_FAILED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function clearUpdateScheduleError()
	{
		$this->csrfProtection();

		$id = $this->input->get->getInt('id', 0);

		/** @var SiteModel $site */
		$tempConfig = [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		];
		$site       = $this->getModel('Site', $tempConfig);

		$site->findOrFail($id);

		try
		{
			/** @var Task $task */
			$task = $this->getModel('Task', $tempConfig);

			$task->findOrFail([
				'site_id' => (int) $id,
				'type'    => 'joomlaupdate',
			]);

			$task->delete();

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_ERROR_CLEARED');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_ERROR_NOT_CLEARED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function clearExtensionUpdatesScheduleError()
	{
		$this->csrfProtection();

		$id = $this->input->get->getInt('id', 0);

		/** @var SiteModel $site */
		$tempConfig = [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		];
		$site       = $this->getModel('Site', $tempConfig);

		$site->findOrFail($id);

		try
		{
			/** @var Task $task */
			$task = $this->getModel('Task', $tempConfig);

			$task->findOrFail([
				'site_id' => (int) $id,
				'type'    => 'extensionsupdate',
			]);

			$task->delete();

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_SCHEDULE_ERROR_CLEARED');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_SCHEDULE_ERROR_NOT_CLEARED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $id)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function scheduleExtensionUpdate()
	{
		$this->csrfProtection();

		$id     = $this->input->get->getInt('id', 0);
		$siteId = $this->input->get->getInt('site_id', 0);

		/** @var SiteModel $site */
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		$site->findOrFail($siteId);

		try
		{
			/** @noinspection PhpParamsInspection */
			if ($this->enqueueExtensionUpdate($site, $id))
			{
				/** @noinspection PhpParamsInspection */
				$this->scheduleExtensionsUpdateForSite($site, $this->container);
			}

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_SCHEDULE_OK');
		}
		catch (Throwable $e)
		{
			$type    = 'error';
			$message = Text::sprintf('PANOPTICON_SITE_ERR_EXTENSION_UPDATE_SCHEDULE_FAILED', $e->getMessage());
		}

		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		if (empty($returnUri))
		{
			$returnUri = $this->container->router->route(
				sprintf('index.php?view=site&task=read&id=%s', $siteId)
			);
		}

		$this->setRedirect($returnUri, $message, $type);
	}

	public function dlkey()
	{
		$this->csrfProtection();

		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if (!$this->canAddEditOrSave($model))
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		// Does the site record exist?
		if ($model->getId() <= 0)
		{
			return false;
		}

		// Does the extension exist?
		$extensions  = (array) $model->getConfig()->get('extensions.list');
		$extensionID = $this->input->getInt('extension', -1);

		if (!isset($extensions[$extensionID]))
		{
			return false;
		}

		$view = $this->getView();

		$view->setDefaultModel($model);
		$view->extension = $extensions[$extensionID];
		$view->setLayout('dlkey');

		parent::display();

		return true;
	}

	public function savedlkey()
	{
		$this->csrfProtection();

		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if (!$this->canAddEditOrSave($model))
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		// Does the site record exist?
		if ($model->getId() <= 0)
		{
			return false;
		}

		// Does the extension exist?
		$extensions  = (array) $model->getConfig()->get('extensions.list');
		$extensionID = $this->input->getInt('extension', -1);

		if (!isset($extensions[$extensionID]))
		{
			return false;
		}

		// Get the Download Key
		$key     = $this->input->getRaw('dlkey');
		$type    = 'info';
		$message = 'The Download Key has been saved';

		try
		{
			$this->getModel()->saveDownloadKey($extensionID, $key);
		}
		catch (Exception $e)
		{
			$type    = 'error';
			$message = $e->getMessage();
		}

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$router = $this->container->router;

		if (!empty($customURL))
		{
			$url = $customURL;
		}
		else
		{
			$url = $router->route(
				sprintf(
					"index.php?view=sites&task=read&id=%d",
					$model->getId()
				)
			);
		}

		$this->setRedirect($url, $message, $type);

		return true;
	}

	protected function onBeforeBrowse(): bool
	{
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		return parent::onBeforeBrowse();
	}

	protected function onBeforeAdd()
	{
		$user = $this->container->userManager->getUser();

		// Can't add sites as a guest.
		if ($user->getId() <= 0)
		{
			return false;
		}

		// To add a site I need one of the super (implied), admin, or addown privileges
		if (!$user->getPrivilege('panopticon.admin') && !$user->getPrivilege('panopticon.addown'))
		{
			return false;
		}

		return parent::onBeforeAdd();
	}

	protected function onBeforeEdit(): bool
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if (!$this->canAddEditOrSave($model))
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		$sysconfigModel = $this->getModel('Sysconfig');
		$this->getView()->setModel('Sysconfig', $sysconfigModel);

		return parent::onBeforeEdit();
	}

	protected function onBeforeApply()
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if (!$this->canAddEditOrSave($model, true))
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		return parent::onBeforeApply();
	}

	protected function onAfterApply()
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		$returnUrl = $this->input->getBase64('returnurl', '');

		$this->setRedirect(
			$this->container->router->route(
				sprintf(
					'index.php?view=site&id=%d&returnurl=%s',
					$model->getId(),
					$returnUrl
				)
			)
		);

		return true;
	}

	protected function onBeforeSave()
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		if (!$this->canAddEditOrSave($model, true))
		{
			throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		return parent::onBeforeSave();
	}

	protected function onBeforeSavenew()
	{
		return false;
	}

	protected function onBeforeCopy()
	{
		return false;
	}

	protected function onBeforeArchive()
	{
		return false;
	}

	protected function onBeforeTrash()
	{
		return false;
	}

	protected function onBeforeOrderdown()
	{
		return false;
	}

	protected function onBeforeOrderup()
	{
		return false;
	}

	protected function onBeforeSaveorder()
	{
		return false;
	}

	/**
	 * Can I edit or save a site?
	 *
	 * @param   SiteModel|null  $site      The site object
	 * @param   bool            $isSaving  Is this about saving a site? FALSE for adding / editing a site.
	 *
	 * @return  bool
	 */
	protected function canAddEditOrSave(?SiteModel $site, bool $isSaving = false): bool
	{
		$user = $this->container->userManager->getUser();

		// Can't edit/save sites as a guest.
		if ($user->getId() <= 0)
		{
			return false;
		}

		// Can't edit sites without an ID
		if (empty($site->getId()) && !$isSaving)
		{
			return false;
		}

		// If I am a superuser I can edit/save any site without any restrictions.
		if ($user->getPrivilege('panopticon.super'))
		{
			return true;
		}

		/**
		 * EXISTING SITE
		 *
		 * If I am here, I have an existing site and I want to know if I can view it for editing, or save changes to it.
		 */

		/**
		 * To edit/save a site I must either have the admin privilege, or it must be my site and I have the editown
		 * privilege. Either way, I also need the view privilege to actually be able to see the site to begin with!
		 */
		$canAdmin   = $user->authorise('panopticon.admin', $site);
		$canSee     = $user->authorise('panopticon.view', $site);
		$canEditOwn = $user->authorise('panopticon.editown', $site) && ($user->getId() === $site->created_by);

		if (!$canSee || !($canAdmin || $canEditOwn))
		{
			return false;
		}

		return true;
	}

	protected function applySave()
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		$user       = $this->container->userManager->getUser();
		$canAdmin   = $user->authorise('panopticon.admin', $model);
		$canEditOwn = $user->authorise('panopticon.editown', $model) && ($user->getId() !== $model->created_by);
		$canAddOwn  = $user->authorise('panopticon.addown', $model);

		$id     = $model->getId() ?: 0;
		$status = true;

		try
		{
			// Handle the API token
			$token = $this->input->getBase64('apiToken', null);

			if (empty($token))
			{
				throw new RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_TOKEN'));
			}

			$config = new Registry($model?->config ?? '{}');
			$config->set('config.apiKey', $token);

			// Get the connection-relevant information BEFORE making any changes to the site
			$currentConnectionInfo = [
				'url'      => $model->url,
				'apiKey'   => $config->get('config.apiKey', ''),
				'username' => $config->get('config.username', ''),
				'password' => $config->get('config.password', ''),
			];

			// Get all the data
			$data = $this->input->getData();

			// Handle the "enabled" field
			$data['enabled'] = in_array(strtolower($data['enabled'] ?? ''), ['on', 'checked', 1, true]);

			// Handle all the config keys
			if (isset($data['config']) && is_array($data['config']) && !empty($data['config']))
			{
				foreach ($data['config'] ?? [] as $key => $value)
				{
					if (in_array($key, self::CHECKBOX_KEYS))
					{
						continue;
					}

					$config->set($key, $value);
				}

				// Handle the checkbox config keys
				foreach (self::CHECKBOX_KEYS as $k)
				{
					$config->set($k, isset($data['config'][$k]));
				}
			}

			// Handle the group assignments, ONLY if I am a superuser or global admin
			if ($user->getPrivilege('panopticon.admin'))
			{
				$groups = $this->input->get('groups', [], 'array');
				$groups = is_array($groups) ? $groups : [$groups];
				$config->set('config.groups', $groups);
			}

			// Apply the config parameters
			$data['config'] = $config->toString('JSON');

			// If I do not have global admin permissions I must not save incoming ownership information
			if (!$user->getPrivilege('panopticon.admin'))
			{
				unset($data['created_by']);
				unset($data['created_on']);
				unset($data['modified_by']);
				unset($data['modified_on']);
			}
			// If this is a new record the owner is the current user
			elseif (empty($model->getId()))
			{
				$data = array_merge([
					'created_by' => $this->container->userManager->getUser()->getId(),
					'created_on' => (new Date())->toSql(),
				], $data);
			}

			// Set the layout to form, if it's not set in the URL
			if (is_null($this->layout))
			{
				$this->layout = 'form';
			}

			if (method_exists($this, 'onBeforeApplySave'))
			{
				$this->onBeforeApplySave($data);
			}

			// Bind the new data
			$model->bind($data);
			$model->check();

			/**
			 * If the user does not have the admin privilege but is saving the site because of the editown or addown
			 * privilege we must make sure that the site is in owned by the current user.
			 */
			if (!$canAdmin && ($canEditOwn || $canAddOwn) && ($user->getId() !== $model->created_by))
			{
				throw new RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
			}

			// Get the connection-relevant information AFTER making changes to the site
			$newConnectionInfo = [
				'url'      => $model->url,
				'apiKey'   => $config->get('config.apiKey', ''),
				'username' => $config->get('config.username', ''),
				'password' => $config->get('config.password', ''),
			];

			// I have to check the connection to the site only if I changed any of its connection-relevant settings
			$mustCheckConnection = false;

			foreach ($currentConnectionInfo as $k => $v)
			{
				$mustCheckConnection = $mustCheckConnection || ($newConnectionInfo[$k] != $v);
			}

			if ($mustCheckConnection)
			{
				$warnings = $model->testConnection(false);
			}

			// Update the Akeeba Backup information if necessary
			if (isset($warnings) && !in_array('akeebabackup', $warnings ?? []))
			{
				$model->testAkeebaBackupConnection();
			}
			else
			{
				$config = $model->getConfig();
				$config->set('akeebabackup.info', null);
				$config->set('akeebabackup.endpoint', null);
				$model->setFieldValue('config', $config->toString());
			}

			// Save the data
			$model->save();

			if (!empty($id))
			{
				$model->unlock();
			}

			// Save the extension update preferences
			if ($model->getId())
			{
				$data = $this->input->get('extupdates', [], 'email');
				$data = is_array($data) ? $data : [];
				/** @var \Akeeba\Panopticon\Model\Sysconfig $sysconfigModel */
				$sysconfigModel = $this->getModel('Sysconfig');
				$sysconfigModel->saveExtensionPreferences($data, $model->getId());

				// Update core information, update extensions information as necessary
				$config = $model->getConfig();

				if (empty($config->get('core.php')))
				{
					$this->doRefreshSiteInformation($model);
				}

				if (empty($config->get('extensions.list')))
				{
					$this->doRefreshExtensionsInformation($model);
				}
			}

			// Call events
			if (method_exists($this, 'onAfterApplySave'))
			{
				$this->onAfterApplySave($data);
			}

			$this->input->set('id', $model->getId());
		}
		catch (SiteConnectionException $e)
		{
			$status = false;
			$this->container->segment->setFlash('site_connection_error', get_class($e));
			$error = Text::_('PANOPTICON_SITES_ERR_CONNECTION_ERROR');
		}
		catch (GuzzleException $e)
		{
			$status = false;
			$this->container->segment->setFlash('site_connection_error', GuzzleException::class);
			$error = Text::_('PANOPTICON_SITES_ERR_CONNECTION_ERROR');
		}
		catch (Throwable $e)
		{
			$status = false;
			$error  = $e->getMessage();
		}

		if ($status)
		{
			$this->container->segment->remove($model->getHash() . 'savedata');

			return true;
		}

		// Cache the item data in the session. We may need to reuse them if the save fails.
		$itemData   = $model->getData();
		$sessionKey = $this->container->application_name . '_' . $this->viewName;
		$this->container->segment->setFlash($sessionKey, $itemData);

		// Redirect on error
		$id = $model->getId();

		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$router = $this->container->router;

		if (!empty($customURL))
		{
			$url = $customURL;
		}
		else
		{
			$task = empty($id) ? 'add' : 'edit';

			$url = $router->route(
				sprintf(
					"index.php?view=%s&task=%s&id=%d",
					$this->view,
					$task,
					intval($id ?? 0)
				)
			);
		}

		$this->setRedirect($url, $error ?? '', 'error');

		return false;
	}

	private function doRefreshExtensionsInformation(Site $site)
	{
		/** @var RefreshSiteInfo $callback */
		$callback = $this->container->taskRegistry->get('refreshinstalledextensions');
		$dummy    = new stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', 1);
		$registry->set('force', true);
		$registry->set('forceUpdates', true);
		$registry->set('filter.ids', [$site->getId()]);

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);
	}

	private function doRefreshSiteInformation(Site $site)
	{
		/** @var RefreshSiteInfo $callback */
		$callback = $this->container->taskRegistry->get('refreshsiteinfo');
		$dummy    = new stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', 1);
		$registry->set('force', true);
		$registry->set('filter.ids', [$site->id]);

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);
	}
}