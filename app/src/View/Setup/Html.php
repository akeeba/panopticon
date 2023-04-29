<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\View\Setup;


use Akeeba\Panopticon\Model\Setup;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use ShowOnTrait;

	public array $reqSettings;

	public bool $requiredMet;

	public array $recommendedSettings;

	public bool $recommendedMet;

	public array $params;

	public array $connectionParameters;

	public function onBeforePrecheck(): bool
	{
		$this->setupPageHeader(subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_PRECHECK'));

		/** @var Setup $model */
		$model = $this->getModel();

		$this->reqSettings         = $model->getRequired();
		$this->requiredMet         = $model->isRequiredMet();
		$this->recommendedSettings = $model->getRecommended();
		$this->recommendedMet      = $model->isRecommendedMet();

		return true;
	}

	public function onBeforeDatabase(): bool
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, async: true);

		$this->setupPageHeader(subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_DATABASE'));

		$this->connectionParameters = $this->getModel()->getDatabaseParameters();

		return true;
	}

	public function onBeforeSession()
	{
		// Get the model
		/** @var Setup $model */
		$model = $this->getModel();

		$this->params = $model->getSetupParameters();

		return true;
	}

	public function onBeforeSetup()
	{
		Template::addJs('media://js/solo/setup.js', $this->getContainer()->application);

		// Set up the page header and toolbar buttons
		$buttons = [
			[
				'title' => Text::_('PANOPTICON_BTN_PREV'),
				'class' => 'akeeba-btn--grey',
				'url'   => Uri::rebase('?view=database', $this->container),
				'icon'  => 'akion-chevron-left',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_NEXT'),
				'class'   => 'akeeba-btn--teal',
				'onClick' => "akeeba.System.triggerEvent('setupFormSubmit', 'click')",
				'icon'    => 'akion-chevron-right',
			],
		];
		$this->setupPageHeader($buttons);

		// Get the model
		/** @var Setup $model */
		$model    = $this->getModel();
		$document = $this->getContainer()->application->getDocument();

		$this->params = $model->getSetupParameters();

		// Language strings communicated to JavaScript
		Text::script('PANOPTICON_COMMON_LBL_ROOT');
		Text::script('COM_AKEEBA_CONFIG_DIRECTFTP_TEST_OK');
		Text::script('COM_AKEEBA_CONFIG_DIRECTFTP_TEST_FAIL');
		Text::script('COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_OK');
		Text::script('COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_FAIL');

		return true;
	}

	/**
	 * Set up the page header
	 *
	 * @param   array  $buttons  An array of button definitions to add to the toolbar
	 *
	 * @return void
	 */
	private function setupPageHeader(array $buttons = [], string $subTitle = ''): void
	{
		$title = Text::_('PANOPTICON_SETUP_TITLE');

		if ($subTitle)
		{
			$title .= sprintf(
				'<small class="ms-1 text-muted"><span class="fa fa-chevron-right" aria-hidden="true"></span></small><small class="ms-2 text-primary-emphasis">%s</small>',
				$subTitle
			);
		}

		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->setTitle($title);

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}
	}
}