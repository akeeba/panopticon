<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Sysconfig;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Mvc\DataView\Html as DataViewHtml;
use Awf\Text\Text;
use Awf\Utils\Template;

class Html extends DataViewHtml
{
	use TimeAgoTrait;
	use ShowOnTrait;
	use CrudTasksTrait
	{
		onBeforeBrowse as onBeforeBrowseCrud;
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	protected Site $item;

	protected ?string $connectionError = null;

	protected ?string $curlError = null;

	protected ?int $httpCode;

	protected array $extUpdatePreferences = [];

	protected array $globalExtUpdatePreferences = [];

	protected string $defaultExtUpdatePreference = 'none';

	public function onBeforeBrowse(): bool
	{
		$result = $this->onBeforeBrowseCrud();

		$user      = $this->container->userManager->getUser();
		$canAdd    = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.addown');
		$canEdit   = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.editown');
		$canDelete = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.editown');
		$buttons   = [];
		$buttons[] = $canAdd ? 'add' : null;
		$buttons[] = $canEdit ? 'edit' : null;
		$buttons[] = $canDelete ? 'delete' : null;

		$this->container->application->getDocument()->getToolbar()->clearButtons();
		$this->addButtons($buttons);

		Template::addJs('media://js/remember-tab.js');

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);

		return $result;
	}

	protected function onBeforeAdd()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, async: true);

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		$this->connectionError = $this->container->segment->getFlash('site_connection_error', null);
		$this->httpCode        = $this->container->segment->getFlash('site_connection_http_code', null);
		$this->curlError       = $this->container->segment->getFlash('site_connection_curl_error', null);

		$document = $this->container->application->getDocument();
		$document->addScriptOptions('panopticon.rememberTab', [
			'key' => 'panopticon.siteAdd.rememberTab',
		]);
		Template::addJs('media://js/remember-tab.js');

		return $this->onBeforeAddCrud();
	}

	protected function onBeforeEdit()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, async: true);

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		/** @var Sysconfig $sysConfigModel */
		$sysConfigModel                   = $this->getModel('Sysconfig');
		$this->extUpdatePreferences       = $sysConfigModel->getExtensionPreferencesAndMeta($this->item->id);
		$this->globalExtUpdatePreferences = $sysConfigModel->getExtensionPreferencesAndMeta(null);
		$this->defaultExtUpdatePreference = $this->container->appConfig->get('tasks_extupdate_install', 'none');

		$this->connectionError = $this->container->segment->getFlash('site_connection_error', null);
		$this->httpCode        = $this->container->segment->getFlash('site_connection_http_code', null);
		$this->curlError       = $this->container->segment->getFlash('site_connection_curl_error', null);

		$this->container->application->getDocument()
			->addScriptOptions('panopticon.rememberTab', [
				'key' => 'panopticon.siteEdit.' . $this->getModel()->id . '.rememberTab',
			]);
		Template::addJs('media://js/remember-tab.js');

		return $this->onBeforeEditCrud();
	}

	protected function onBeforeRead(): bool
	{
		$this->addButton('prev', ['url' => $this->container->router->route('index.php?view=main')]);

		$this->setTitle(Text::_('PANOPTICON_SITES_TITLE_READ'));

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		$document = $this->container->application->getDocument();

		$document->addScriptOptions('panopticon.rememberTab', [
			'key' => 'panopticon.siteRead.' . $this->getModel()->id . '.rememberTab',
		]);
		Template::addJs('media://js/remember-tab.js');

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$document->addScriptDeclaration($js);

		return true;
	}
}