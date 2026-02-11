<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;

class Policies extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function tos(): void
	{
		$this->getView()->setLayout('default');

		$this->display();
	}

	public function privacy(): void
	{
		$this->getView()->setLayout('privacy');

		$this->display();
	}

	public function edit(): void
	{
		$this->getView()->setLayout('edit');

		$this->display();
	}

	public function save(): void
	{
		$this->csrfProtection();

		/** @var \Akeeba\Panopticon\Model\Policies $model */
		$model    = $this->getModel();
		$type     = $this->input->getCmd('type', 'tos');
		$language = $this->input->getCmd('language', 'en-GB');
		$html     = $this->input->post->getRaw('content', '');

		$model->setContent($type, $language, $html);

		$this->setRedirect(
			$this->container->router->route(
				sprintf('index.php?view=policies&task=edit&type=%s&language=%s', $type, $language)
			),
			$this->getLanguage()->text('PANOPTICON_POLICIES_MSG_SAVED')
		);
	}

	public function cancel(): void
	{
		$this->setRedirect(
			$this->container->router->route('index.php?view=sysconfig')
		);
	}
}
