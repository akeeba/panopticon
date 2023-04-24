<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Akeeba\Component\Panopticon\Api\Model\CoreModel;
use Joomla\CMS\MVC\Controller\ApiController;

class CoreController extends ApiController
{
	protected $contentType = 'coreupdate';

	protected $default_view = 'core';

	public function getupdate()
	{
		$force  = $this->input->getInt('force', 0) === 1;

		$this->input->set('model', 'core');

		$this->modelState->panopticon_mode = 'core.update';
		$this->modelState->panopticon_force = $force;

		return $this->displayItem();
	}
}