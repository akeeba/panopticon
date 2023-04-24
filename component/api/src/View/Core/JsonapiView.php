<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Core;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Akeeba\Component\Panopticon\Api\Model\CoreModel;
use Joomla\CMS\MVC\View\JsonApiView as BaseJsonApiView;

class JsonapiView extends BaseJsonApiView
{
	public function displayItem($item = null)
	{
		/** @var CoreModel $model */
		$model = $this->getModel();

		$mode = $model->getState('panopticon_mode');

		if ($mode === 'core.update')
		{
			$this->fieldsToRenderItem = [
				'current',
				'latest',
				'details',
				'info',
				'changelog',
				'extensionAvailable',
				'updateSiteAvailable',
				'maxCacheHours',
				'minimumStability',
				'updateSiteUrl',
				'lastUpdateTimestamp',
			];
		}

		return parent::displayItem($item);
	}
}