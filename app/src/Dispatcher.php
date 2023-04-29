<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon;

use Awf\Dispatcher\Dispatcher as AWFDispatcher;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Dispatcher extends AWFDispatcher
{
	public function onBeforeDispatch(): bool
	{
		$this->loadCommonCSS();
		$this->loadCommonJavaScript();

		return true;
	}

	private function loadCommonJavaScript(): void
	{
		Template::addJs('media://js/bootstrap.bundle.min.js', defer: true);
		Template::addJs('media://js/system.min.js', defer: true);
		Template::addJs('media://js/ajax.min.js', defer: true);
	}

	private function loadCommonCSS()
	{
		Template::addCss('media://css/theme.min.css');
		Template::addCss('media://css/fontawesome.min.css');
	}
}