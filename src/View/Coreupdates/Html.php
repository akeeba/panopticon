<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Coreupdates;

defined('AKEEBA') || die;

use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Inflector\Inflector;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Text\Text;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	public function onBeforeBrowse(): bool
	{
		$this->addButtonFromDefinition(
			[
				'id'      => 'updateSelected',
				'title'   => Text::_('PANOPTICON_COREUPDATES_BTN_UPDATE'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'scheduledUpdates\')',
				'icon'    => 'fa fa-wand-magic-sparkles',
			]
		);
		$this->addButtonFromDefinition(
			[
				'id'      => 'cancelSelected',
				'title'   => Text::_('PANOPTICON_COREUPDATES_BTN_CANCEL'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'cancelUpdates\')',
				'icon'    => 'fa fa-stop',
			]
		);

		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE'));

		// If no list limit is set, use the Panopticon default (50) instead of All (AWF's default).
		$limit = $this->getModel()->getState('limit', 50, 'int');
		$this->getModel()->setState('limit', $limit);

		$this->addTooltipJavaScript();

		return parent::onBeforeBrowse();
	}

	private function addTooltipJavaScript(): void
	{
		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"],[data-bs-tooltip="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);
	}

}