<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\DataController;

class Mailtemplates extends DataController
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function editcss()
	{
		$this->getView()->setLayout('css');

		$this->display();
	}

	public function savecss()
	{
		$this->csrfProtection();

		/** @var \Akeeba\Panopticon\Model\Mailtemplates $model */
		$model = $this->getModel();
		$css = $this->input->post->getRaw('css', '');
		$model->setCommonCSS($css);

		$this->setRedirect($this->container->router->route('index.php?view=mailtemplates'));
	}
}