<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\SiteConnectionException;
use Akeeba\Panopticon\Library\Task\Status;
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
use GuzzleHttp\Exception\GuzzleException;

class Sites extends DataController
{
	use ACLTrait;
	use EnqueueJoomlaUpdateTrait;
	use EnqueueExtensionUpdateTrait;

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
		catch (\Throwable $e)
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

		$id   = $this->input->get->getInt('id', 0);
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		try
		{
			$site->findOrFail($id);

			/** @var RefreshSiteInfo $callback */
			$callback = $this->container->taskRegistry->get('refreshsiteinfo');
			$dummy    = new \stdClass();
			$registry = new Registry();

			$registry->set('limitStart', 0);
			$registry->set('limit', 1);
			$registry->set('force', true);
			$registry->set('filter.ids', [$id]);

			do
			{
				$return = $callback($dummy, $registry);
			} while ($return === Status::WILL_RESUME->value);

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_REFRESHED_OK');
		}
		catch (\Throwable $e)
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

		$id   = $this->input->get->getInt('id', 0);
		$site = $this->getModel('Site', [
			'modelTemporaryInstance' => true,
			'modelClearState'        => true,
			'modelClearInput'        => true,
		]);

		try
		{
			$site->findOrFail($id);

			/** @var RefreshSiteInfo $callback */
			$callback = $this->container->taskRegistry->get('refreshinstalledextensions');
			$dummy    = new \stdClass();
			$registry = new Registry();

			$registry->set('limitStart', 0);
			$registry->set('limit', 1);
			$registry->set('force', true);
			$registry->set('forceUpdates', true);
			$registry->set('filter.ids', [$id]);

			do
			{
				$return = $callback($dummy, $registry);
			} while ($return === Status::WILL_RESUME->value);

			$type    = 'info';
			$message = Text::_('PANOPTICON_SITE_LBL_EXTENSIONS_REFRESHED_OK');
		}
		catch (\Throwable $e)
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
		catch (\Throwable $e)
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
		catch (\Throwable $e)
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
		catch (\Throwable $e)
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
		catch (\Throwable $e)
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

		if (!$this->canAddEditOrSave($model, null))
		{
			throw new \RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
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

		$groups = $this->input->get('groups', [], 'array');
		$groups = is_array($groups) ? $groups : [$groups];

		if (!$this->canAddEditOrSave($model, $groups, true))
		{
			throw new \RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		return parent::onBeforeApply();
	}

	protected function onBeforeSave()
	{
		/** @var SiteModel $model */
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

		$groups = $this->input->get('groups', [], 'array');
		$groups = is_array($groups) ? $groups : [$groups];

		if (!$this->canAddEditOrSave($model, $groups, true))
		{
			throw new \RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
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
	 * @param   SiteModel|null  $site       The site object
	 * @param   array|null      $newGroups  The groups we want to assign the site to (only applies to saving)
	 * @param   bool            $isSaving   Is this about saving a site? FALSE for adding / editing a site.
	 *
	 * @return  bool
	 */
	protected function canAddEditOrSave(?SiteModel $site, ?array $newGroups, bool $isSaving = false): bool
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

		/**
		 * If the site already has groups attached, the user needs to belong to all of them.
		 *
		 * Otherwise, saving the site would require dropping some groups the user does not belong to.
		 */
		$config = $site->getFieldValue('config') instanceof Registry
			? $site->getFieldValue('config')
			: new Registry($site->getFieldValue('config') ?: '{}');
		$groups = $config->get('config.groups', []) ?: [];
		$groups = is_array($groups) ? $groups : [];

		if (empty($groups))
		{
			// The site had no groups. I can edit it.
			return true;
		}

		// Get the group IDs the user belongs to
		$groupPrivileges = $user->getGroupPrivileges();
		$possibleGroups  = array_keys($groupPrivileges);

		if (empty($possibleGroups))
		{
			// The user has no access to any groups, but the site belongs to some groups. Cannot edit/save.
			return false;
		}

		// If the user does not have access to all groups the site is already assigned to we can't edit/save.
		if (array_values(array_intersect($groups, $possibleGroups)) !== array_values($groups))
		{
			return false;
		}

		// If we are not saving there is nothing else to check.
		if (!$isSaving)
		{
			return true;
		}

		// If we are asked to reassign the site to new groups, make sure the user has access to them
		if (!empty($newGroups) && array_values(array_intersect($newGroups, $possibleGroups)) !== array_values($newGroups))
		{
			return false;
		}

		/**
		 * Make sure that the new group selection won't make the user lose their current access to the site.
		 *
		 * We only check for privileges the user does not have globally, but which are only granted per-site by the
		 * user's group membership.
		 *
		 * Reasoning: if I have a global privilege I don't care if I remove the site from a group I belong to which also
		 * grants me the same privilege. Since I have it globally, I will retain it. If, however, I don't have a global
		 * privilege then the only thing which allows me to interact with the site is the per-site privilege granted
		 * to me by group membership. If I remove the site from this group, I lose my privilege to the site. This would
		 * make the site inaccessible to me, and I'd have to call an admin or superuser to get me out of the mess I
		 * created for myself. So, we have to prevent that!
		 */

		// Which is the current user's access to the site?
		$currentPrivileges = [];

		if ($user->authorise('panopticon.admin', $site) && !$user->getPrivilege('panopticon.admin'))
		{
			$currentPrivileges[] = 'panopticon.admin';
		}

		if ($user->authorise('panopticon.view', $site) && !$user->getPrivilege('panopticon.view'))
		{
			$currentPrivileges[] = 'panopticon.view';
		}

		if ($user->authorise('panopticon.run', $site) && !$user->getPrivilege('panopticon.view'))
		{
			$currentPrivileges[] = 'panopticon.run';
		}

		// No per-site privileges needed. Okay then. Nothing to do here.
		if (empty($currentPrivileges))
		{
			return true;
		}

		// Let's keep the privileges for the groups the user has access to, and they selected this site should belong to.
		$groupPrivileges = array_filter(
			$groupPrivileges,
			fn(int $id) => in_array($id, $newGroups),
			ARRAY_FILTER_USE_KEY
		);

		// Do these groups give us all the privileges we need? Loop for each necessary privilege.
		foreach ($currentPrivileges as $privName)
		{
			// For each privilege we check if at least one group grants it to us. If not, we can't proceed with save.
			if (
				!array_reduce(
					$groupPrivileges,
					fn(bool $carry, array $item) => $carry || in_array($privName, $item),
					false
				)
			)
			{
				return false;
			}
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

		$user = $this->container->userManager->getUser();
		$canAdmin   = $user->authorise('panopticon.admin', $model);
		$canEditOwn = $user->authorise('panopticon.editown', $model) && ($user->getId() !== $model->created_by);
		$canAddOwn = $user->authorise('panopticon.addown', $model);

		$id     = $model->getId() ?: 0;
		$status = true;

		try
		{
			// Handle the API token
			$token = $this->input->getBase64('apiToken', null);

			if (empty($token))
			{
				throw new \RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_TOKEN'));
			}

			$config = new \Awf\Registry\Registry($model?->config ?? '{}');
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

			// Handle the group assignments
			$groups = $this->input->get('groups', [], 'array');
			$groups = is_array($groups) ? $groups : [$groups];
			$config->set('config.groups', $groups);

			// Apply the config parameters
			$data['config'] = $config->toString('JSON');

			$data = array_merge([
				'created_by' => $this->container->userManager->getUser()->getId(),
				'created_on' => (new Date())->toSql(),
			], $data);

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
				throw new \RuntimeException(Text::_('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
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
				$model->testConnection(false);
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
				$data = $this->input->get('extupdates', [], 'none');
				$data = is_array($data) ? $data : [];
				/** @var \Akeeba\Panopticon\Model\Sysconfig $sysconfigModel */
				$sysconfigModel = $this->getModel('Sysconfig');
				$sysconfigModel->saveExtensionPreferences($data, $model->getId());
			}

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
		catch (\Throwable $e)
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
}