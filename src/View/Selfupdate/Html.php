<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Selfupdate;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\SelfUpdate\UpdateInformation;
use Akeeba\Panopticon\Library\SelfUpdate\VersionInformation;
use Akeeba\Panopticon\Model\Selfupdate;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Text\Text;

class Html extends BaseHtmlView
{
	public bool $force = false;

	public ?UpdateInformation $updateInformation = null;

	public ?VersionInformation $latestversion = null;

	public $hasUpdate = false;

	public function onBeforeMain(): bool
	{
		/** @var Selfupdate $model */
		$model = $this->getModel();

		$this->updateInformation = $model->getUpdateInformation($this->force);
		$this->latestversion     = $model->getLatestVersion();
		$this->hasUpdate         = $model->hasUpdate();

		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->addButtonFromDefinition([
			'title' => Text::_('PANOPTICON_BTN_PREV'),
			'class' => 'btn btn-secondary border-light',
			'url'   => $this->container->router->route('index.php?view=main'),
			'icon'  => 'fa fa-chevron-left',
		]);

		$toolbar->setTitle(Text::_('PANOPTICON_SELFUPDATE_TITLE'));

		return true;
	}
}