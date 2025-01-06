<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Mailtemplates;

defined('AKEEBA') || die;

use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as DataViewHtml;

class Html extends DataViewHtml
{
	use CrudTasksTrait {
		onBeforeBrowse as onBeforeBrowseCrud;
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	public string $css;

	public function onBeforeBrowse()
	{
		$result = $this->onBeforeBrowseCrud();

		$this->addButtonFromDefinition([
			'id'    => 'editCss',
			'title' => $this->getLanguage()->text('PANOPTICON_MAILTEMPLATES_BTN_CSS'),
			'class' => 'btn btn-primary',
			'url'   => $this->container->router->route('index.php?view=mailtemplates&task=editcss'),
			'icon'  => 'fab fa-css3-alt',
		]);

		return $result;
	}

	protected function onBeforeAdd()
	{
		$result = $this->onBeforeAddCrud();

		$this->css = $this->getModel()->getCommonCss();

		return $result;
	}

	protected function onBeforeEdit()
	{
		$result = $this->onBeforeEditCrud();

		$this->css = $this->getModel()->getCommonCss();

		return $result;
	}

	public function onBeforeEditcss(): bool
	{
		$this->addButtons(['save', 'cancel']);

		$this->setTitle($this->getLanguage()->text('PANOPTICON_MAILTEMPLATES_TITLE_CSS'));

		$this->css = $this->getModel()->getCommonCss();

		return true;
	}
}