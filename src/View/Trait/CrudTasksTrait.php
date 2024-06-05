<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Trait;


use Awf\Inflector\Inflector;

defined('AKEEBA') || die;

trait CrudTasksTrait
{
	public function onBeforeBrowse(): bool
	{
		$this->addButtons(['add', 'edit', 'copy', 'delete']);

		if (empty($this->getTitle()))
		{
			$this->setTitle($this->getLanguage()->text('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE'));
		}

		// If no list limit is set, use the Panopticon default (50) instead of All (AWF's default).
		$limit = $this->getModel()->getState('limit', 50, 'int');
		$this->getModel()->setState('limit', $limit);

		return parent::onBeforeBrowse();
	}

	protected function onBeforeAdd()
	{
		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->addButtons(['save', 'apply', 'cancel']);

		if (empty($this->getTitle()))
		{
			$this->setTitle($this->getLanguage()->text('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE_NEW'));
		}

		return parent::onBeforeAdd();
	}

	protected function onBeforeEdit()
	{
		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->addButtons(['save', 'apply', 'cancel']);

		if (empty($this->getTitle()))
		{
			$this->setTitle($this->getLanguage()->text('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE_EDIT'));
		}

		return parent::onBeforeEdit();
	}

	protected function addButtons(array $buttons)
	{
		array_walk(
			$buttons,
			function (array|string|null $button) {
				is_array($button) ? $this->addButtonFromDefinition($button) : $this->addButton($button);
			}
		);
	}

	protected function addButton(?string $type, array $params = []): void
	{
		$buttonDef = match ($type)
		{
			'add' => [
				'id'      => 'add',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_ADD'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'add') . '\')',
				'icon'    => 'fa fa-plus',
			],
			'edit' => [
				'id'      => 'edit',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_EDIT'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'edit') . '\')',
				'icon'    => 'fa fa-pen-to-square',
			],
			'batch' => [
				'id'      => 'batch',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_BATCH'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => json_encode(
					[
						'data-bs-toggle' => 'modal',
						'data-bs-target' => '#batchModal'
					]
				),
				'icon'    => 'fa fa-solid fa-square',
			],
			'copy' => [
				'id'      => 'copy',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_COPY'),
				'class'   => 'btn btn-secondary border-light',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'copy') . '\')',
				'icon'    => 'fa fa-clone',
			],
			'delete' => [
				'id'      => 'delete',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_DELETE'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'remove') . '\')',
				'icon'    => 'fa fa-trash-can',
			],
			'publish' => [
				'id'      => 'publish',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_ENABLE'),
				'class'   => 'btn btn-dark',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'publish') . '\')',
				'icon'    => 'fa fa-circle-check',
			],
			'unpublish' => [
				'id'      => 'unpublish',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_DISABLE'),
				'class'   => 'btn btn-dark',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'unpublish') . '\')',
				'icon'    => 'fa fa-circle-xmark',
			],
			'save' => [
				'id'      => 'save',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_SAVE'),
				'class'   => 'btn btn-primary',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'save') . '\');',
				'icon'    => 'fa fa-save',
			],
			'apply' => [
				'id'      => 'apply',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_APPLY'),
				'class'   => 'btn btn-success',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'apply') . '\');',
				'icon'    => 'fa fa-check',
			],
			'cancel' => [
				'id'      => 'cancel',
				'title'   => $this->getLanguage()->text('PANOPTICON_BTN_CANCEL'),
				'class'   => 'btn btn-danger',
				'onClick' => 'akeeba.System.submitForm(\'' . ($params['task'] ?? 'cancel') . '\');',
				'icon'    => 'fa fa-cancel',
			],
			'back' => [
				'id'    => 'back',
				'title' => $this->getLanguage()->text('PANOPTICON_BTN_PREV'),
				'class' => 'btn btn-secondary border-light',
				'url'   => $params['url'],
				'icon'  => 'fa fa-chevron-left',
			],
			'inlineHelp' => [
				'id'      => 'inlineHelp',
				'title'   => $this->getLanguage()->text('PANOPTICON_APP_LBL_SHOW_HIDE_HELP'),
				'class'   => 'btn-info',
				'onClick' => json_encode(
					[
						'data-bs-toggle' => "collapse",
						'data-bs-target' => ".form-text",
						'aria-expanded'  => "false",
					]
				),
				'icon'    => 'fa fa-question-circle me-1',
			],
			default => null
		};

		if (empty($buttonDef))
		{
			return;
		}

		if (isset($params['class']))
		{
			$buttonDef['class'] .= ' ' . $params['class'];
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

	protected function getTitle(): string
	{
		return $this->container->application->getDocument()->getToolbar()->getTitle() ?: '';
	}
}