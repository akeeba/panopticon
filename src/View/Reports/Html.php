<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Reports;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Pagination\Pagination;
use stdClass;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;
	use TimeAgoTrait;

	protected bool $hasSiteFilter;

	protected Pagination $pagination;

	protected function onBeforeMain(): bool
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_REPORTS_TITLE'));

		/** @var Reports $model */
		$model = $this->getModel();
		$model->savestate(1);

		$this->lists             = new stdClass();
		$this->lists->limitStart = (int) $model->getState('limitstart', 0);
		$this->lists->limit      = (int) $model->getState('limit', 20);

		$model->setState('filter_order', 'created_on');
		$model->setState('filter_order_Dir', 'ASC');
		$model->setState('limitstart', $this->lists->limitStart);
		$model->setState('limit', $this->lists->limit);

		$this->items      = $model->get();
		$this->itemsCount = $model->count();

		$displayedLinks   = 10;
		$this->pagination = new Pagination(
			$this->itemsCount, $this->lists->limitStart, $this->lists->limit, $displayedLinks, $this->container
		);

		$this->hasSiteFilter = $model->getState('site_id', null) > 0;

		return true;
	}
}