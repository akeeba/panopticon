<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Akeeba\Component\Panopticon\Api\Model\UpdatesiteModel;
use Akeeba\Component\Panopticon\Api\Model\UpdatesitesModel;
use Akeeba\Component\Panopticon\Api\View\Updatesites\JsonapiView;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\CheckinCheckout;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Controller\Exception\Save;
use RuntimeException;

class UpdatesitesController extends ApiController
{
	protected $contentType = 'updatesites';

	protected $default_view = 'updatesites';

	public function execute($task)
	{
		$this->app->getLanguage()
			->load('com_installer', JPATH_ADMINISTRATOR);

		return parent::execute($task);
	}

	public function displayList()
	{
		$eid     = $this->input->get('eid', []);
		$eid     = is_array($eid) ? $eid : [];
		$enabled = $this->input->getCmd('enabled', '');
		$enabled = $enabled !== '' ? @intval($enabled) : '';

		$this->modelState->set('filter.eid', $eid);
		$this->modelState->set('filter.enabled', $enabled);

		return parent::displayList();
	}

	public function rebuild()
	{
		/** @var UpdatesitesModel $model */
		$model = $this->getModel('Updatesites');

		if (!$model)
		{
			throw new RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		$this->app->getMessageQueue(true);

		$model->rebuild();

		$messages = $this->app->getMessageQueue();
		$errors   = array_filter(
			array_map(
				function (array $item) {
					if ($item['type'] !== 'error')
					{
						return null;
					}

					return [
						'title' => $item['message'],
						'code'  => 500,
					];
				},
				$messages
			)
		);

		if (count($errors))
		{
			$this->app->getDocument()->setErrors($errors);
			$this->app->setHeader('status', 400);

			return $this;
		}

		$this->app->setHeader('status', 200);
	}

	protected function save($recordKey = null)
	{
		/** @var UpdatesiteModel $model */
		$model = $this->getModel('updatesite');

		if (!$model)
		{
			throw new RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		try
		{
			$table = $model->getTable();
		}
		catch (Exception $e)
		{
			throw new RuntimeException($e->getMessage());
		}

		if (!$table->load($recordKey))
		{
			throw new ResourceNotFound();
		}

		$key  = $table->getKeyName();
		$data = $this->input->get('data', json_decode($this->input->json->getRaw(), true), 'array');

		foreach (array_keys($data) as $key)
		{
			if (!in_array($key, ['enabled', 'extra_query']))
			{
				unset ($data['key']);
			}
		}

		$item = $model->getItem($recordKey);
		$data = array_merge($item->getProperties(), $data);

		if (isset($data['extra_query']))
		{
			$data['extra_query'] = $item->downloadIdPrefix . $data['extra_query'] . $item->downloadIdSuffix;
		}

		// Attempt to save the data.
		if (!$model->save($data))
		{
			throw new Save(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()));
		}

		// Save succeeded, so check-in the record.
		if ($model->checkin($recordKey) === false)
		{
			throw new CheckinCheckout(Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
		}

		return $recordKey;
	}

	/**
	 * Method to check if you can edit an existing record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param   array   $data  An array of input data.
	 * @param   string  $key   The name of the key for the primary key; default is id.
	 *
	 * @return  boolean
	 *
	 * @since   4.0.0
	 */
	protected function allowEdit($data = [], $key = 'id')
	{
		return $this->app->getIdentity()->authorise('core.edit', 'com_installer');
	}

	/**
	 * Method to check if you can add a new record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param   array  $data  An array of input data.
	 *
	 * @return  boolean
	 *
	 * @since   4.0.0
	 */
	protected function allowAdd($data = [])
	{
		return false;
	}
}