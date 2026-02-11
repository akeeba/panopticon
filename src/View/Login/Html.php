<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Login;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Passkeys;
use Awf\Mvc\View;
use Awf\Utils\Template;

class Html extends View
{
	public ?string $username = null;

	public ?string $password = null;

	public ?string $secret = null;

	public ?string $autologin = null;

	public ?string $returnUrl = null;

	public bool $hasPasskeys = false;

	public bool $registrationEnabled = false;

	public function onBeforeMain()
	{
		Template::addJs('media://js/login.min.js', $this->getContainer()->application, defer: true);

		$doc = $this->getContainer()->application->getDocument();

		$doc->addScriptOptions(
			'login.url',
			$this->getContainer()->router->route('index.php?view=login&lang=')
		);

		$this->container->input->set('tmpl', 'component');

		$this->registrationEnabled = $this->container->appConfig->get('user_registration', 'disabled') !== 'disabled';

		$this->username  = $this->container->segment->getFlash('auth_username');
		$this->password  = $this->container->segment->getFlash('auth_password');
		$this->secret    = $this->container->segment->getFlash('auth_secret');
		$this->autologin = $this->container->segment->getFlash('auto_login');

		$router = $this->getContainer()->router;
		$token  = $this->getContainer()->session->getCsrfToken()->getValue();

		/** @var Passkeys $passkeysModel */
		$passkeysModel     = $this->getContainer()->mvcFactory->makeTempModel('Passkeys');
		$this->hasPasskeys = $passkeysModel->isEnabled();

		if ($this->hasPasskeys)
		{
			$doc->lang('PANOPTICON_PASSKEYS_ERR_INVALID_USERNAME');
			$doc->addScriptOptions(
				'passkey', [
					'challengeURL' => $router->route(
						sprintf('index.php?view=passkeys&task=challenge&format=json&%s=1', $token)
					),
					'loginURL'     => $router->route(
						sprintf('index.php?view=passkeys&task=login&format=raw&%s=1', $token)
					),
				]
			);
		}


		return true;
	}
}