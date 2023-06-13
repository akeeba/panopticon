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

			if (!$user->authorise('panopticon.admin', $model))
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

		if (!$user->authorise('panopticon.admin', $model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			$model->akeebaBackupDelete($backupId);

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

		if (!$user->authorise('panopticon.admin', $model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			$model->akeebaBackupDeleteFiles($backupId);

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
		// TODO Implement me
	}

	public function akeebaBackupCancel(): bool
	{
		// TODO Implement me
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