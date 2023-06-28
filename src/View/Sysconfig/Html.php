<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Sysconfig;


use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Text\Text;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use CrudTasksTrait;

	protected array $extUpdatePreferences = [];

	protected function onBeforeMain(): bool
	{
		$this->extUpdatePreferences = $this->getModel()->getExtensionPreferencesAndMeta();

		// Load JavaScript
		Template::addJs('media://js/showon.js');

		// Create a save and apply button in the toolbar
		$this->addButton('save');
		$this->addButton('apply');
		$this->addButtonFromDefinition([
			'id'    => 'phpinfo',
			'title' => Text::_('PANOPTICON_SYSCONFIG_BTN_PHPINFO'),
			'class' => 'btn btn-warning',
			'url'   => $this->container->router->route('index.php?view=phpinfo'),
			'icon'  => 'fa fa-info-circle',
		]);
		$this->addButton('cancel');

		$document = $this->container->application->getDocument();

		$document->getMenu()->disableMenu('main');
		$document->addScriptOptions('panopticon.rememberTab', [
			'key' => 'panopticon.sysconfig.rememberTab'
		]);
		Template::addJs('media://js/remember-tab.js');

		return true;
	}
}