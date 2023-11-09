<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Awf\Uri\Uri;
use Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

defined('AKEEBA') || die;

trait AkeebaBackupIntegrationTrait
{
	public function akeebaBackupRelink(): bool
	{
		$this->csrfProtection();

		$id = $this->input->getInt('id', null);

		if (empty($id))
		{
			return false;
		}

		if (!$this->akeebaBackupRelinkInternal($id))
		{
			return false;
		}

		$this->setRedirectWithMessage(
			$this->container->router->route(sprintf('index.php?view=site&task=read&id=%d&akeebaBackupForce=1', $id))
		);

		return true;
	}

	public function akeebaBackupDelete(): bool
	{
		$this->csrfProtection();

		$id       = $this->input->getInt('id', null);
		$backupId = $this->input->getInt('backup_id', null);

		if (empty($id) || $id <= 0 || empty($backupId) || $backupId <= 0)
		{
			return false;
		}

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			// Delete the record
			$model->akeebaBackupDelete($backupId);

			// Bust the cache
			$from  = $model->getState('akeebaBackupFrom', 0, 'int');
			$limit = $model->getState('akeebaBackupLimit', 20, 'int');
			$key   = sprintf('backupList-%d-%d-%d', $model->id, $from, $limit);
			/** @var CacheInterface $pool */
			$pool = $this->container->cacheFactory->pool('akeebabackup');
			$pool->delete($key);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$id,
					'akeebabackup.delete',
					true,
					$backupId
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function akeebaBackupDeleteFiles(): bool
	{
		$this->csrfProtection();

		$id       = $this->input->getInt('id', null);
		$backupId = $this->input->getInt('backup_id', null);

		if (empty($id) || $id <= 0 || empty($backupId) || $backupId <= 0)
		{
			return false;
		}

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			// Delete the files
			$model->akeebaBackupDeleteFiles($backupId);

			// Bust the cache
			$from  = $model->getState('akeebaBackupFrom', 0, 'int');
			$limit = $model->getState('akeebaBackupLimit', 20, 'int');
			$key   = sprintf('backupList-%d-%d-%d', $model->id, $from, $limit);
			/** @var CacheInterface $pool */
			$pool = $this->container->cacheFactory->pool('akeebabackup');
			$pool->delete($key);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$id,
					'akeebabackup.deleteFiles',
					true,
					$backupId
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function reloadBoU()
	{
		$this->csrfProtection();

		$id               = $this->input->getInt('id', null);
		$reloadExtensions = $this->input->getBool('extensions', false);
		$relink           = $this->input->getBool('relink', false);

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
		{
			return false;
		}

		if ($reloadExtensions)
		{
			try
			{
				$this->doRefreshExtensionsInformation($model, forceUpdates: false);
			}
			catch (Exception $e)
			{
			}
		}

		if ($relink)
		{
			try
			{
				$this->akeebaBackupRelinkInternal($id);
			}
			catch (Exception $e)
			{
			}
		}

		$view = $this->getView();
		$view->setTask('reloadBoU');
		$view->setDoTask('reloadBoU');
		$view->setLayout('form_akeebabackup');
		$view->setStrictTpl(true);
		$view->setStrictLayout(true);
		$view->setDefaultModel($model);
		$view->display();

		return true;
	}

	public function akeebaBackupProfilesSelect()
	{
		$this->csrfProtection();

		$selected = $this->input->getInt('selected', 1);
		$id       = $this->input->getInt('id', null);

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
		{
			return false;
		}

		$profiles = $model->akeebaBackupGetProfiles(false);

		echo $this->getContainer()->html->select->genericList(
			data: array_combine(
				array_map(fn($p) => $p->id, $profiles),
				array_map(fn($p) => sprintf('#%d. %s', $p->id, $p->name), $profiles),
			),
			name: 'config[config.core_update.backup_profile]',
			attribs: [
				'class' => 'form-control',
			],
			selected: $selected,
			idTag: 'backupOnUpdateProfiles'
		);

		return true;
	}

	public function akeebaBackupEnqueue(): bool
	{
		$this->csrfProtection();

		$id        = $this->input->getInt('id', null);
		$profileId = $this->input->getInt('profile_id', null);

		if (empty($id) || $id <= 0 || empty($profileId) || $profileId <= 0)
		{
			return false;
		}

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.run', $model)
		    && !$user->authorise('panopticon.admin', $model)
		    && !$canEditMine)
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			$model->akeebaBackupEnqueue($profileId, user: $this->getContainer()->userManager->getUser());

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	private function akeebaBackupRelinkInternal(int $id): bool
	{
		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();
		$db    = $this->container->db;

		try
		{
			$db->lockTable('#__sites');
			$model->findOrFail($id);

			$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

			if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
			{
				$db->unlockTables();

				return false;
			}

			$dirty = $model->testAkeebaBackupConnection(true);

			if ($dirty)
			{
				$model->save();
			}
		}
		catch (Throwable)
		{
			return false;
		}
		finally
		{
			$db->unlockTables();
		}

		return true;
	}

	private function setRedirectWithMessage(string $defaultReturnURL, ?string $message = null, ?string $type = null)
	{
		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		$this->setRedirect($returnUri ?: $defaultReturnURL, $message, $type);
	}
}