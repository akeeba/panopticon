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

	public string $cronKey;

	public int $maxExec;

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

		$buttons = [
			[
				'title'   => Text::_('PANOPTICON_APP_LBL_SHOW_HIDE_HELP'),
				'class'   => 'btn-info',
				'onClick' => json_encode([
					'data-bs-toggle' => "collapse", 'data-bs-target' => ".form-text", 'aria-expanded' => "false",
				]),
				'icon'    => 'fa fa-question-circle me-1',
			],
		];

		$this->setupPageHeader($buttons, subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_DATABASE'));

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
		$buttons = [
			[
				'title'   => Text::_('PANOPTICON_APP_LBL_SHOW_HIDE_HELP'),
				'class'   => 'btn-info',
				'onClick' => json_encode([
					'data-bs-toggle' => "collapse", 'data-bs-target' => ".form-text", 'aria-expanded' => "false",
				]),
				'icon'    => 'fa fa-question-circle me-1',
			],
		];

		$this->setupPageHeader($buttons, subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_SETUP'));

		$this->params = $this->getModel()->getSetupParameters();

		Text::script('PANOPTICON_COMMON_LBL_ROOT');
		Text::script('PANOPTICON_CONFIG_DIRECTFTP_TEST_OK');
		Text::script('PANOPTICON_CONFIG_DIRECTFTP_TEST_FAIL');
		Text::script('PANOPTICON_CONFIG_DIRECTSFTP_TEST_OK');
		Text::script('PANOPTICON_CONFIG_DIRECTSFTP_TEST_FAIL');

		return true;
	}

	public function onBeforeCron()
	{
		$this->setupPageHeader(subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_CRON'));

		$this->cronKey = $this->container->appConfig->get('webcron_key', '');

		return true;
	}

	public function onBeforeFinish()
	{
		$this->setupPageHeader(subTitle: Text::_('PANOPTICON_SETUP_SUBTITLE_FINISH'));

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