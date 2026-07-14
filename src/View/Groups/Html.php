<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Groups;

defined('AKEEBA') || die;

use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Utils\Template;

class Html extends BaseHtmlView
{
	use CrudTasksTrait {
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	protected function onBeforeAdd()
	{
		$result = $this->onBeforeAddCrud();

		Template::addJs('media://js/group-colour.js', $this->getContainer()->application, defer: true);

		return $result;
	}

	protected function onBeforeEdit()
	{
		$result = $this->onBeforeEditCrud();

		Template::addJs('media://js/group-colour.js', $this->getContainer()->application, defer: true);

		return $result;
	}
}