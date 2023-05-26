<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Phpinfo;

use Awf\Text\Text;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	public ?string $phpInfo;

	public function onBeforeMain(): bool
	{
		$this->container->application->getDocument()->getToolbar()->addButtonFromDefinition([
			'title'   => Text::_('PANOPTICON_BTN_PREV'),
			'class'   => 'btn btn-secondary border-light',
			'url' => $this->container->router->route('index.php?view=sysconfig'),
			'icon'    => 'fa fa-chevron-left',
		]);

		$this->phpInfo = $this->getModel()->getPhpInfo();

		return true;
	}
}