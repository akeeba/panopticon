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
use Awf\Date\Date;
use Awf\Mvc\DataController;
use Awf\Text\Text;
use GuzzleHttp\Exception\GuzzleException;

class Sites extends DataController
{
	use ACLTrait;

	private const CHECKBOX_KEYS = [
		'config.core_update.email_error',
		'config.core_update.email_after',
	];

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	protected function onBeforeBrowse(): bool
	{
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		return parent::onBeforeBrowse();
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