<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Extupdates;


use Akeeba\Panopticon\Model\Extupdates;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Pagination\Pagination;
use Awf\Text\Text;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use CrudTasksTrait;

	public array $sites = [];

	public array $scheduledPerSite = [];

	public function onBeforeMain()
	{
		$this->setTitle(Text::_('PANOPTICON_EXTUPDATES_TITLE'));
		$this->addButtonFromDefinition(
			[
				'id'      => 'updateSelected',
				'title'   => Text::_('PANOPTICON_EXTUPDATES_BTN_UPDATE'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'update') . '\')',
				'icon'    => 'fa fa-wand-magic-sparkles',
			]
		);

		$this->lists = new \stdClass();

		/** @var Extupdates $model */
		$model = $this->getModel();

		$this->lists->order      = $model->getState('filter_order', 'id', 'cmd');
		$this->lists->order_Dir  = $model->getState('filter_order_Dir', 'ASC', 'cmd');
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('limit', 50, 'int');

		$this->items      = $model->getExtensions(false, $this->lists->limitStart, $this->lists->limit);
		$this->itemsCount = $model->getTotalExtensions();

		$displayedLinks   = 10;
		$this->pagination = new Pagination(
			$this->itemsCount, $this->lists->limitStart, $this->lists->limit, $displayedLinks, $this->container
		);

		$this->sites = array_map(
			function ($site_id) {
				/** @var Site $site */
				$site = clone $this->getModel('site');
				try
				{
					$site                             = $site->findOrFail($site_id);
					$this->scheduledPerSite[$site_id] = $site->getExtensionsScheduledForUpdate();
				}
				catch (\Exception $e)
				{
					return null;
				}

				return $site;
			},
			$model->getSiteIds()
		);

		foreach ($this->items as $e)
		{
			$site_id = $e->site_id;

			if (isset($this->sites[$site_id]))
			{
				continue;
			}

			/** @var Site $site */
			$site = clone $this->getModel('site');
			try
			{
				$this->sites[$site_id]            = $site->findOrFail($site_id);
				$this->scheduledPerSite[$site_id] = $this->sites[$site_id]->getExtensionsScheduledForUpdate();
			}
			catch (\Exception $e)
			{
				continue;
			}
		}

		$this->addTooltipJavaScript();

		return true;
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