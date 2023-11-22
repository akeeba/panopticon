<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Login;

defined('AKEEBA') || die;

use Awf\Mvc\View;
use Awf\Utils\Template;

class Html extends View
{
	public ?string $username;

	public ?string $password;

	public ?string $secret;

	public ?string $autologin;

	public ?string $returnUrl;

	public function onBeforeMain()
	{
		Template::addJs('media://js/login.min.js', $this->getContainer()->application, defer: true);

		$this->getContainer()->application->getDocument()
			->addScriptOptions(
				'login.url',
				$this->getContainer()->router->route('index.php?view=login&lang=')
			);

		$this->container->input->set('tmpl', 'component');

		$this->username  = $this->container->segment->getFlash('auth_username');
		$this->password  = $this->container->segment->getFlash('auth_password');
		$this->secret    = $this->container->segment->getFlash('auth_secret');
		$this->autologin = $this->container->segment->getFlash('auto_login');

		return true;
	}
}