<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Policies;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Policies;
use Awf\Mvc\View;

class Html extends View
{
	public string $policyContent = '';

	public string $policyType = 'tos';

	public string $policyLanguage = 'en-GB';

	public array $availableLanguages = [];

	public function onBeforeDefault(): bool
	{
		return $this->onBeforeTos();
	}

	public function onBeforeTos(): bool
	{
		$this->container->input->set('tmpl', 'component');
		$this->setTitle($this->getLanguage()->text('PANOPTICON_POLICIES_TITLE_TOS'));

		/** @var Policies $model */
		$model = $this->getModel();

		$this->policyType    = 'tos';
		$this->policyContent = $model->getContent('tos');

		return true;
	}

	public function onBeforePrivacy(): bool
	{
		$this->container->input->set('tmpl', 'component');
		$this->setTitle($this->getLanguage()->text('PANOPTICON_POLICIES_TITLE_PRIVACY'));

		/** @var Policies $model */
		$model = $this->getModel();

		$this->policyType    = 'privacy';
		$this->policyContent = $model->getContent('privacy');

		return true;
	}

	public function onBeforeEdit(): bool
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_POLICIES_TITLE_EDIT'));

		/** @var Policies $model */
		$model = $this->getModel();

		$this->policyType     = $this->container->input->getCmd('type', 'tos');
		$this->policyLanguage = $this->container->input->getCmd('language', 'en-GB');
		$this->policyContent  = $model->getContent($this->policyType, $this->policyLanguage);

		$this->availableLanguages = $model->getAvailableLanguages($this->policyType);

		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=sysconfig')]);

		return true;
	}
}
