<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\View\Main;


defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	protected function onBeforeMain()
	{
		$this->onBeforeBrowse();

		$inlineJs = <<< JS
(() => {
    window.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
	const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))  
    })
})()

JS;
		$this->container->application->getDocument()->addScriptDeclaration($inlineJs);

		return true;
	}
}