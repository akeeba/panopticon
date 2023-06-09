<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Trait;


use Awf\Inflector\Inflector;
use Awf\Text\Text;

defined('AKEEBA') || die;

trait CrudTasksTrait
{
	public function onBeforeBrowse(): bool
	{
		$this->addButtons(['add', 'edit', 'copy', 'delete']);

		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE'));

		// If no list limit is set, use the Panopticon default (50) instead of All (AWF's default).
		$limit = $this->getModel()->getState('limit', 50, 'int');
		$this->getModel()->setState('limit', $limit);

		return parent::onBeforeBrowse();
	}

	protected function onBeforeAdd()
	{
		$this->addButtons(['save', 'apply', 'cancel']);

		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE_NEW'));

		return parent::onBeforeAdd();
	}

	protected function onBeforeEdit()
	{
		$this->addButtons(['save', 'apply', 'cancel']);

		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE_EDIT'));

		return parent::onBeforeEdit();
	}

	protected function addButtons(array $buttons)
	{
		array_walk($buttons, function (array|string|null $button) {
			is_array($button) ? $this->addButtonFromDefinition($button) : $this->addButton($button);
		});
	}

	protected function addButton(?string $type, array $params = []): void
	{
		$buttonDef = match ($type)
		{
			'add' => [
				'title'   => Text::_('PANOPTICON_BTN_ADD'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'add\')',
				'icon'    => 'fa fa-plus',
			],
			'edit' => [
				'title'   => Text::_('PANOPTICON_BTN_EDIT'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'edit\')',
				'icon'    => 'fa fa-pen-to-square',
			],
			'copy' => [
				'title'   => Text::_('PANOPTICON_BTN_COPY'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'copy\')',
				'icon'    => 'fa fa-clone',
			],
			'delete' => [
				'title'   => Text::_('PANOPTICON_BTN_DELETE'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'remove\')',
				'icon'    => 'fa fa-trash-can',
			],
			'publish' => [
				'title'   => Text::_('PANOPTICON_BTN_ENABLE'),
				'class'   => 'btn btn-dark',
				'onClick' => 'akeeba.System.submitForm(\'publish\')',
				'icon'    => 'fa fa-circle-check',
			],
			'unpublish' => [
				'title'   => Text::_('PANOPTICON_BTN_DISABLE'),
				'class'   => 'btn btn-dark',
				'onClick' => 'akeeba.System.submitForm(\'unpublish\')',
				'icon'    => 'fa fa-circle-xmark',
			],
			'save' => [
				'title'   => Text::_('PANOPTICON_BTN_SAVE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.System.submitForm(\'save\');',
				'icon'    => 'fa fa-save',
			],
			'apply' => [
				'title'   => Text::_('PANOPTICON_BTN_APPLY'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'apply\');',
				'icon'    => 'fa fa-check',
			],
			'cancel' => [
				'title'   => Text::_('PANOPTICON_BTN_CANCEL'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'cancel\');',
				'icon'    => 'fa fa-cancel',
			],
			'back' => [
				'title' => Text::_('PANOPTICON_BTN_PREV'),
				'class' => 'btn btn-secondary border-light',
				'url'   => $params['url'],
				'icon'  => 'fa fa-chevron-left',
			],
			'inlineHelp' => [
				'title'   => Text::_('PANOPTICON_APP_LBL_SHOW_HIDE_HELP'),
				'class'   => 'btn-info',
				'onClick' => json_encode([
					'data-bs-toggle' => "collapse", 'data-bs-target' => ".form-text", 'aria-expanded' => "false",
				]),
				'icon'    => 'fa fa-question-circle me-1',
			],
			default => null
		};

		if (empty($buttonDef))
		{
			return;
		}

		$this->addButtonFromDefinition($buttonDef);
	}

	protected function addButtonFromDefinition(array $button): void
	{
		$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition($button);
	}

	protected function setTitle(string $title): void
	{
		$this->container->application->getDocument()->getToolbar()
			->setTitle($title);
	}
}