<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Phpinfo;

use Akeeba\Panopticon\View\Trait\CrudTasksTrait;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	use CrudTasksTrait;

	public ?string $phpInfo = null;

	public function onBeforeMain(): bool
	{
		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=sysconfig')]);

		$this->phpInfo = $this->getModel()->getPhpInfo();

		return true;
	}
}