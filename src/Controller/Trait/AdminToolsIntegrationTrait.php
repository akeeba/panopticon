<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;


use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Awf\Utils\Ip;
use Throwable;

defined('AKEEBA') || die;

trait AdminToolsIntegrationTrait
{
	public function admintoolsPluginDisable(): bool
	{
		$this->csrfProtection();

		$model = $this->admintoolsGetSiteModelFromRequest();

		if (empty($model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(
			sprintf('index.php?view=site&task=read&id=%d', $model->getId())
		);

		try
		{
			$result = $model->adminToolsPluginDisable();

			if ($result->didChange)
			{
				$this->saveSite(
					$model,
					function (Site $site) use ($result)
					{
						$config = $site->getConfig();
						$config->set('core.admintools.renamed', $result->renamed);
						$site->setFieldValue('config', $config->toString());
					}
				);
			}

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$model->getId(),
					'admintools.pluginDisable',
					true
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Throwable $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function admintoolsPluginEnable(): bool
	{
		$this->csrfProtection();

		$model = $this->admintoolsGetSiteModelFromRequest();

		if (empty($model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(
			sprintf('index.php?view=site&task=read&id=%d', $model->getId())
		);

		try
		{
			$result = $model->adminToolsPluginEnable();

			if ($result->didChange)
			{
				$this->saveSite(
					$model,
					function (Site $model) use ($result) {
						$config = $model->getConfig();
						$config->set('core.admintools.renamed', $result->renamed);
						$model->setFieldValue('config', $config->toString());
					}
				);
			}

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$model->getId(),
					'admintools.pluginEnable',
					true
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Throwable $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function admintoolsHtaccessDisable(): bool
	{
		$this->csrfProtection();

		$model = $this->admintoolsGetSiteModelFromRequest();

		if (empty($model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(
			sprintf('index.php?view=site&task=read&id=%d', $model->getId())
		);

		try
		{
			$model->adminToolsHtaccessDisable();

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$model->getId(),
					'admintools.htaccessDisable',
					true
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Throwable $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function admintoolsHtaccessEnable(): bool
	{
		$this->csrfProtection();

		$model = $this->admintoolsGetSiteModelFromRequest();

		if (empty($model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(
			sprintf('index.php?view=site&task=read&id=%d', $model->getId())
		);

		try
		{
			$model->adminToolsHtaccessEnable();

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$model->getId(),
					'admintools.htaccessEnable',
					true
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Throwable $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function admintoolsUnblockMyIp(): bool
	{
		$this->csrfProtection();

		$model = $this->admintoolsGetSiteModelFromRequest();

		if (empty($model))
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(
			sprintf('index.php?view=site&task=read&id=%d', $model->getId())
		);

		$myIp = Ip::getUserIP();

		try
		{
			$model->adminToolsUnblockIP($myIp);

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);

			// Add a report log entry
			try
			{
				Reports::fromSiteAction(
					$model->getId(),
					'admintools.unblockMyIP',
					true,
					$myIp
				)->save();
			}
			catch (Throwable)
			{
				// Whatever
			}
		}
		catch (Throwable $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	public function adminToolsEnqueue(): bool
	{
		$this->csrfProtection();

		$id        = $this->input->getInt('id', null);

		if (empty($id) || $id <= 0)
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
			$model->adminToolsScanEnqueue($this->getContainer()->userManager->getUser());

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (\Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}

	private function admintoolsGetSiteModelFromRequest(): ?Site
	{
		$id = $this->input->getInt('id', null);

		if (empty($id))
		{
			return null;
		}

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (!$user->authorise('panopticon.admin', $model) && !$canEditMine)
		{
			return null;
		}

		return $model;
	}
}