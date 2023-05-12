<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\View\Main;

use Akeeba\Panopticon\Model\Site;
use Awf\Pagination\Pagination;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	protected function onBeforeMain()
	{
		// Create the lists object
		$this->lists = new \stdClass();

		// Load the model
		/** @var Site $model */
		$model = $this->getModel();
		$model->setState('enabled', 1);

		// We want to persist the state in the session
		$model->savestate(1);

		// Ordering information
		$this->lists->order		 = $model->getState('filter_order', $model->getIdFieldName(), 'cmd');
		$this->lists->order_Dir	 = $model->getState('filter_order_Dir', 'DESC', 'cmd');

		// Display limits
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('limit', 50, 'int');

		// Assign items to the view
		$this->items      = $model->get();
		$this->itemsCount = $model->count();

		// Pagination
		$displayedLinks = 10;
		$this->pagination = new Pagination($this->itemsCount, $this->lists->limitStart, $this->lists->limit, $displayedLinks, $this->container->application);

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