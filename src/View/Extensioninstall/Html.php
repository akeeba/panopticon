<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Extensioninstall;

use Akeeba\Panopticon\Model\Extensioninstall;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Pagination\Pagination;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use CrudTasksTrait;

	public $items = [];

	public int $itemsCount = 0;

	public array $groupMap = [];

	public $lists;

	public Pagination $pagination;

	/** @var Site[] */
	public array $selectedSites = [];

	public array $cmsTypeInfo = [];

	public array $versionInfo = [];

	public function onBeforeMain()
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_TITLE'));

		$this->addButtonFromDefinition(
			[
				'id'      => 'continueToReview',
				'title'   => $this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_CONTINUE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.ExtensionInstall.submitSelection()',
				'icon'    => 'fa fa-arrow-right',
			]
		);

		// Groups map
		$this->groupMap = $this->getModel('groups')->getGroupMap();

		// Create the lists object
		$this->lists = new \stdClass();

		// Load the Site DataModel
		/** @var Site $siteModel */
		$siteModel = $this->getModel('site');
		$siteModel->setState('enabled', 1);
		$siteModel->savestate(1);

		// Display limits
		$this->lists->limitStart = $siteModel->getState('limitstart', 0, 'int');
		$this->lists->limit      = $siteModel->getState('limit', 50, 'int');

		$siteModel->setState('filter_order', 'name');
		$siteModel->setState('filter_order_Dir', 'ASC');
		$siteModel->setState('limitstart', $this->lists->limitStart);
		$siteModel->setState('limit', $this->lists->limit);

		// Get items and count
		$this->itemsCount = $siteModel->count();
		$this->items      = $siteModel->get();

		// Pagination
		$this->pagination = new Pagination(
			$this->itemsCount, $this->lists->limitStart, $this->lists->limit, 10, $this->container
		);

		return true;
	}

	public function onBeforeReview()
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_TITLE'));

		$this->addButtonFromDefinition(
			[
				'id'      => 'goBack',
				'title'   => $this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_GOBACK'),
				'class'   => 'btn btn-secondary',
				'url'     => $this->container->router->route('index.php?view=extensioninstall'),
				'icon'    => 'fa fa-arrow-left',
			]
		);

		$siteIds = $this->container->segment->get('extensioninstall.sites', []);

		if (empty($siteIds))
		{
			return true;
		}

		/** @var Extensioninstall $model */
		$model = $this->getModel();

		$this->selectedSites = $model->getSitesById($siteIds);
		$this->cmsTypeInfo   = $model->validateSiteCmsTypes($this->selectedSites);
		$this->versionInfo   = $model->validateSiteVersions($this->selectedSites);

		return true;
	}
}
