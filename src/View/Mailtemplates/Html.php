<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Mailtemplates;

defined('AKEEBA') || die;

use Awf\Mvc\DataView\Html as DataViewHtml;
use Awf\Text\Text;

class Html extends DataViewHtml
{
	public string $css;

	public function onBeforeBrowse()
	{
		$document = $this->container->application->getDocument();
		$toolbar  = $document->getToolbar();
		$buttons  = [
			[
				'title'   => Text::_('PANOPTICON_BTN_ADD'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'add\')',
				'icon'    => 'fa fa-plus',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_EDIT'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'edit\')',
				'icon'    => 'fa fa-pen-to-square',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_COPY'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'copy\')',
				'icon'    => 'fa fa-clone',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_DELETE'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'remove\')',
				'icon'    => 'fa fa-trash-can',
			],
			[
				'title'   => Text::_('PANOPTICON_MAILTEMPLATES_BTN_CSS'),
				'class'   => 'btn btn-primary',
				'url' => $this->container->router->route('index.php?view=mailtemplates&task=editcss'),
				'icon'    => 'fab fa-css3-alt',
			],
		];

		array_walk($buttons, function (array $button) {
			$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition($button);
		});

		$toolbar->setTitle(Text::_('PANOPTICON_MAILTEMPLATES_TITLE'));

		return parent::onBeforeBrowse();
	}


	protected function onBeforeAdd()
	{
		$document = $this->container->application->getDocument();
		$toolbar  = $document->getToolbar();
		$buttons = [
			[
				'title'   => Text::_('PANOPTICON_BTN_SAVE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.System.submitForm(\'save\');',
				'icon'    => 'fa fa-save',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_APPLY'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'apply\');',
				'icon'    => 'fa fa-check',
			],
			[
				'title' => Text::_('PANOPTICON_BTN_CANCEL'),
				'class' => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'cancel\');',
				'icon'  => 'fa fa-cancel',
			],
		];

		array_walk($buttons, function (array $button) {
			$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition($button);
		});

		$toolbar->setTitle(Text::_('PANOPTICON_MAILTEMPLATES_TITLE_ADD'));

		$this->css = $this->getModel()->getCommonCss();

		return parent::onBeforeAdd();
	}

	protected function onBeforeEdit()
	{
		$document = $this->container->application->getDocument();
		$toolbar  = $document->getToolbar();
		$buttons = [
			[
				'title'   => Text::_('PANOPTICON_BTN_SAVE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.System.submitForm(\'save\');',
				'icon'    => 'fa fa-save',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_APPLY'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'apply\');',
				'icon'    => 'fa fa-check',
			],
			[
				'title' => Text::_('PANOPTICON_BTN_CANCEL'),
				'class' => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'cancel\');',
				'icon'  => 'fa fa-cancel',
			],
		];

		array_walk($buttons, function (array $button) {
			$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition($button);
		});

		$toolbar->setTitle(Text::_('PANOPTICON_MAILTEMPLATES_TITLE_EDIT'));

		$this->css = $this->getModel()->getCommonCss();

		return parent::onBeforeEdit();
	}

	public function onBeforeEditcss(): bool
	{
		$toolbar = $this->container->application->getDocument()->getToolbar();

		$buttons = [
			[
				'title'   => Text::_('PANOPTICON_BTN_SAVE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.System.submitForm(\'savecss\');',
				'icon'    => 'fa fa-save',
			],
			[
				'title' => Text::_('PANOPTICON_BTN_CANCEL'),
				'class' => 'btn btn-danger',
				'url' => $this->container->router->route('index.php?view=mailtemplates'),
				'icon'  => 'fa fa-cancel',
			],
		];
		array_walk($buttons, function (array $button) {
			$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition($button);
		});

		$toolbar->setTitle(Text::_('PANOPTICON_MAILTEMPLATES_TITLE_CSS'));

		$this->css = $this->getModel()->getCommonCss();

		return true;
	}
}