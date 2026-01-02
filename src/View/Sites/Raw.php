<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\View\Trait\ShowOnTrait;

class Raw extends \Awf\Mvc\DataView\Raw
{
	use ShowOnTrait;

	public function onBeforeReloadBoU(): bool
	{
		$this->item = $this->getModel();

		return true;
	}
}