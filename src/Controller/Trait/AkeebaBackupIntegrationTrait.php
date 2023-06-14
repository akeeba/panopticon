<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

use Akeeba\Panopticon\Model\Site;
use Awf\Uri\Uri;

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
		catch (\Throwable)
		{
			return false;
		}
		finally
		{
			$db->unlockTables();
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
			/** @var \Symfony\Contracts\Cache\CacheInterface $pool */
			$pool  = $this->container->cacheFactory->pool('akeebabackup');
			$pool->delete($key);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (\Exception $e)
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
			/** @var \Symfony\Contracts\Cache\CacheInterface $pool */
			$pool  = $this->container->cacheFactory->pool('akeebabackup');
			$pool->delete($key);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (\Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

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

		if (
			!$user->authorise('panopticon.run', $model)
			&& !$user->authorise('panopticon.admin', $model)
			&& !$canEditMine
		)
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			$model->akeebaBackupEnqueue($profileId);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (\Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
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