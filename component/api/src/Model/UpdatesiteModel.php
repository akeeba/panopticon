<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

use Joomla\CMS\Form\Form;

(defined('AKEEBA') || defined('_JEXEC')) || die;

class UpdatesiteModel extends \Joomla\Component\Installer\Administrator\Model\UpdatesiteModel
{
	public function getForm($data = [], $loadData = true)
	{
		// We have to do that since we're essentially hijacking com_installer's model from a different component.
		$path = JPATH_ADMINISTRATOR . '/components/com_installer/';

		Form::addFormPath($path . '/forms');
		Form::addFormPath($path . '/models/forms');
		Form::addFieldPath($path . '/models/fields');
		Form::addFormPath($path . '/model/form');
		Form::addFieldPath($path . '/model/field');

		return parent::getForm($data, $loadData);
	}


	public function getItem($pk = null)
	{
		try
		{
			$item = parent::getItem($pk);
		}
		catch (\Throwable $e)
		{
			return new \stdClass();
		}

		$item->id = $item->update_site_id;

		return $item;
	}

}