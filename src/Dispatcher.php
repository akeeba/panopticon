<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon;

use Awf\Dispatcher\Dispatcher as AWFDispatcher;
use Awf\Input\Filter;
use Awf\Uri\Uri;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Dispatcher extends AWFDispatcher
{
	public function __construct($container = null)
	{
		parent::__construct($container);

		if (empty(Uri::getInstance()->getVar('view')))
		{
			Uri::getInstance()->setVar('view', $this->view);
		}
	}


	public function dispatch()
	{
		try
		{
			parent::dispatch();
		}
		catch (\Exception $e)
		{
			// Access Denied: redirect to the login page
			if ($e->getCode() === 403 && !$this->container->userManager->getUser()->getId())
			{
				$returnUrl = base64_encode(Uri::getInstance()->toString());
				$redirectUrl = $this->container->router->route(
					sprintf('index.php?view=login&return=%s', $returnUrl)
				);

				$this->container->application->redirect($redirectUrl);

				// Just for static analysis. Not really necessary; the statement above halts execution and redirects.
				return;
			}

			throw $e;
		}
	}


	public function onBeforeDispatch(): bool
	{
		$this->loadCommonCSS();
		$this->loadCommonJavaScript();

		return true;
	}

	private function loadCommonJavaScript(): void
	{
		Template::addJs('media://js/bootstrap.bundle.min.js', defer: true);
		Template::addJs('media://js/system.min.js', defer: false);
		Template::addJs('media://js/ajax.min.js', defer: true);
	}

	private function loadCommonCSS(): void
	{
		$themeFile = $this->container->appConfig->get('theme', 'theme') ?: 'theme';
		$themeFile = (new Filter())->clean($themeFile, 'path');
		$themeFile .= '.min.css';

		if (!@file_exists(Template::parsePath('media://css/' . $themeFile)))
		{
			$themeFile = 'theme.min.css';
		}

		Template::addCss('media://css/' . $themeFile);
		Template::addCss('media://css/fontawesome.min.css');
	}
}