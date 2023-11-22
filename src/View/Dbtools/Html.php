<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Dbtools;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Dbtools;
use Akeeba\Panopticon\Model\Trait\FormatFilesizeTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use FormatFilesizeTrait;

	protected array $files;

	public function onBeforeStartBackup(): bool
	{
		return $this->onBeforeBackup();
	}

	public function onBeforeBackup(): bool
	{
		$this->setLayout('backup');

		return true;
	}

	public function onBeforeMain(): bool
	{
		$toolbar = $this->getContainer()->application->getDocument()->getToolbar();

		$toolbar->setTitle($this->getLanguage()->text('PANOPTICON_DBTOOLS_TITLE'));

		/** @var Dbtools $model */
		$model       = $this->getModel();
		$this->files = $model->getFiles();

		$this->addTooltipJavaScript();

		return true;
	}

	/**
	 * @return  void
	 * @since   1.0.3
	 */
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