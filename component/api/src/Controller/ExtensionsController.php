<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Akeeba\Component\Panopticon\Api\Model\ExtensionsModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\View\JsonApiView;
use Joomla\String\Inflector;

class ExtensionsController extends ApiController
{
	protected $contentType = 'extensions';

	protected $default_view = 'extensions';

	public function displayList()
	{
		foreach ([
			         'updatable',
			         'protected',
			         'core',
		         ] as $key)
		{
			$value = $this->input->get->get($key, null);

			if ($value !== null && $value !== '')
			{
				$this->modelState->set('filter.' . $key, intval($value));
			}
		}

		return parent::displayList();
	}

	/**
	 * Basic display of an item view
	 *
	 * @param   integer  $id  The primary key to display. Leave empty if you want to retrieve data from the request
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @since   4.0.0
	 */
	public function displayItem($id = null)
	{
		if ($id === null) {
			$id = $this->input->get('id', 0, 'int');
		}

		if (is_int($id) && $id === 0)
		{
			$element = $this->input->getCmd('element', '');
			/** @var ExtensionsModel $model */
			$model = $this->getModel('Extensions', 'Api', ['ignore_request' => true]);
			$id = $model->getExtensionIdFromElement($element);
		}

		if ($id === 0 || $id === null)
		{
			throw new ResourceNotFound();
		}

		return parent::displayItem($id);
	}

}