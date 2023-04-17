<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Akeeba\Component\Panopticon\Api\Model\UpdatesiteModel;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Tobscure\JsonApi\Exception\InvalidParameterException;

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

	protected function save($recordKey = null)
	{
		/** @var UpdatesiteModel $model */
		$model = $this->getModel('updatesite');

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		try
		{
			$table = $model->getTable();
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException($e->getMessage());
		}

		if (!$table->load($recordKey))
		{
			throw new ResourceNotFound();
		}

		$key        = $table->getKeyName();
		$data       = $this->input->get('data', json_decode($this->input->json->getRaw(), true), 'array');

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
			throw new \Joomla\CMS\MVC\Controller\Exception\Save(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()));
		}

		// Save succeeded, so check-in the record.
		if ($model->checkin($recordKey) === false)
		{
			throw new \Joomla\CMS\MVC\Controller\Exception\CheckinCheckout(Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
		}

		return $recordKey;
	}


}