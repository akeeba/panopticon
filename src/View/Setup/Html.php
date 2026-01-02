<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Setup;


use Akeeba\Panopticon\Model\Setup;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use ShowOnTrait;
	use CrudTasksTrait;

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
		$this->setupPageHeader(subTitle: $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_PRECHECK'));

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
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

		$this->addButton('inlineHelp');

		$this->setupPageHeader(subTitle: $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_DATABASE'));

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
		$this->addButton('inlineHelp');

		$this->setupPageHeader(subTitle: $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_SETUP'));

		$this->params = $this->getModel()->getSetupParameters();

		$document = $this->getContainer()->application->getDocument();

		$document->lang('PANOPTICON_COMMON_LBL_ROOT');
		$document->lang('PANOPTICON_CONFIG_DIRECTFTP_TEST_OK');
		$document->lang('PANOPTICON_CONFIG_DIRECTFTP_TEST_FAIL');
		$document->lang('PANOPTICON_CONFIG_DIRECTSFTP_TEST_OK');
		$document->lang('PANOPTICON_CONFIG_DIRECTSFTP_TEST_FAIL');

		return true;
	}

	public function onBeforeCron()
	{
		$this->setupPageHeader(subTitle: $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_CRON'));

		$this->cronKey = $this->container->appConfig->get('webcron_key', '');

		return true;
	}

	public function onBeforeFinish()
	{
		$this->setupPageHeader(subTitle: $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_FINISH'));

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
		$title = $this->getLanguage()->text('PANOPTICON_SETUP_TITLE');

		if ($subTitle)
		{
			$title .= sprintf(
				' - <small>%s</small>',
				$subTitle
			);
		}

		$this->setTitle($title);
		$this->addButtons($buttons);
	}
}