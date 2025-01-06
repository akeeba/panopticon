<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Actionsummarytasks;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtml;


class Html extends BaseHtml
{
	use CrudTasksTrait
	{
		onBeforeBrowse as onBeforeBrowseCrud;
		onBeforeEdit as onBeforeEditCrud;
		onBeforeAdd as onBeforeAddCrud;
	}

	public Site $site;

	public function onBeforeBrowse()
	{
		$return = $this->onBeforeBrowseCrud();

		$this->container->application->getDocument()->getToolbar()->clearButtons();

		$this->addButton(
			'back',
			[
				'url' => $this->container->router->route(
					sprintf('index.php?view=sites&task=read&id=%d', $this->site->id)
				),
				'class' => 'me-3'
			]
		);
		$this->addButton('add');
		$this->addButton('edit');
		$this->addButton('delete');

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);

		return $return;
	}

	protected function onBeforeAdd()
	{
		$return = $this->onBeforeAddCrud();

		$this->addButton('inlineHelp');

		return $return;
	}


	protected function onBeforeEdit()
	{
		$return = $this->onBeforeEditCrud();

		$this->addButton('inlineHelp');

		return $return;
	}

}