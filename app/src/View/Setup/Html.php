<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\View\Setup;


use Akeeba\Panopticon\Model\Setup;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	public array $reqSettings;

	public bool $reqMet;

	public array $recommendedSettings;

	public bool $recMet;

	public array $params;

	public array $connectionParameters;

	public function onBeforePrecheck(): bool
	{
		$this->setupPageHeader();

		// Get the model
		/** @var Setup $model */
		$model = $this->getModel();

		// Push data from the model
		$this->reqSettings         = $model->getRequired();
		$this->reqMet              = $model->isRequiredMet();
		$this->recommendedSettings = $model->getRecommended();
		$this->recMet              = $model->isRecommendedMet();

		return true;
	}

	public function onBeforeSession()
	{
		Template::addJs('media://js/solo/setup.js', $this->getContainer()->application);

		// Get the model
		/** @var Setup $model */
		$model = $this->getModel();

		$this->params = $model->getSetupParameters();

		return true;
	}

	public function onBeforeDatabase()
	{
		Template::addJs('media://js/solo/setup.js', $this->getContainer()->application);

		// Set up the page header and toolbar buttons
		$buttons = [
			[
				'title' => Text::_('PANOPTICON_BTN_PREV'),
				'class' => 'akeeba-btn--grey',
				'url'   => Uri::rebase('?view=setup&view=precheck', $this->container),
				'icon'  => 'akion-chevron-left',
			],
			[
				'title'   => Text::_('PANOPTICON_BTN_NEXT'),
				'class'   => 'akeeba-btn--teal',
				'onClick' => "akeeba.System.triggerEvent('dbFormSubmit', 'click')",
				'icon'    => 'akion-chevron-right',
			],
		];
		$this->setupPageHeader($buttons);

		// Get the model
		/** @var Setup $model */
		$model = $this->getModel();

		// Push data from the model
		$this->connectionParameters = $model->getDatabaseParameters();

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
	private function setupPageHeader($buttons = [])
	{
		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->setTitle(Text::_('PANOPTICON_SETUP_TITLE'));

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}
	}
}