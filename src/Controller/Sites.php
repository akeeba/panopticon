<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Exception\SiteConnectionException;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
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

	private const CHECKBOX_KEYS = [
		'config.core_update.email_error',
		'config.core_update.email_after',
	];

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
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

			$callback->setLogger($this->container->logger);

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
			$registry->set('filter.ids', [$id]);

			$callback->setLogger($this->container->logger);

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

		/** @var Site $site */
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

		/** @var Site $site */
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
				'site_id' => (int)$id,
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

	protected function onBeforeBrowse(): bool
	{
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		return parent::onBeforeBrowse();
	}

	protected function onBeforeEdit(): bool
	{
		$sysconfigModel = $this->getModel('Sysconfig');
		$this->getView()->setModel('Sysconfig', $sysconfigModel);

		return parent::onBeforeEdit();
	}

	protected function applySave()
	{
		$model = $this->getModel();

		if (!$model->getId())
		{
			$this->getIDsFromRequest($model, true);
		}

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

			// Make sure we can successfully connect to the site
			$model->bind($data);
			$model->check();
			$model->testConnection(false);

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