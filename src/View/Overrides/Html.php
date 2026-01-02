<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Overrides;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Overrides;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Pagination\Pagination;
use Awf\Utils\Template;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	public Site $site;

	public function onBeforeBrowse()
	{
		$this->addButton(
			'back',
			[
				'url' => $this->container->router->route(
					sprintf(
						'index.php?view=site&task=read&id=%d',
						$this->site->id
					)
				)
			]
		);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_OVERRIDES_TITLE'));

		$this->getModel()->setSite($this->site);

		if (!parent::onBeforeBrowse())
		{
			return false;
		}

		$this->lists->limitStart = $this->getModel()->getState('limitstart', 0);
		$this->lists->limit = $this->getModel()->getState('limit', 20);

		// Pagination
		$displayedLinks = 10;
		$this->pagination = new Pagination($this->itemsCount, $this->lists->limitStart, $this->lists->limit, $displayedLinks, $this->container);

		return true;
	}

	protected function onBeforeRead()
	{
		$this->addButton(
			'back',
			[
				'url' => $this->container->router->route(
					sprintf(
						'index.php?view=overrides&site_id=%d',
						$this->site->id
					)
				)
			]
		);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_OVERRIDES_TITLE_READ'));

		/** @var Overrides $model */
		$model = $this->getModel();
		$model->setSite($this->site);

		$this->item = $model->getItem();

		if (!parent::onBeforeRead())
		{
			return false;
		}

		$document = $this->container->application->getDocument();
		$document->addScriptOptions(
			'panopticon.rememberTab', [
				'key' => 'panopticon.overrideRead.rememberTab',
			]
		);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		return true;
	}


}