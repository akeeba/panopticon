<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;
use Awf\Uri\Uri;
use Exception;
use Throwable;

class Login extends Controller
{
	public function login(): bool
	{
		try
		{
			$this->csrfProtection();

			// Get the username and password from the request
			$username = $this->input->get('username', '', 'raw');
			$password = $this->input->get('password', '', 'raw');
			$secret   = $this->input->get('secret', '', 'raw');

			// Try to    log in the user
			$manager = $this->container->userManager;
			$manager->loginUser($username, $password, ['secret' => $secret]);

			// Redirect to the saved return_url or, if none specified, to the application's main page
			$url    = $this->getReturnUrl();
			$router = $this->container->router;

			if (empty($url))
			{
				$url = $router->route('index.php?view=main');
			}

			$this->setRedirect($url);
		}
		catch (Exception $e)
		{
			$router = $this->container->router;

			// Login failed. Go back to the login page and show the error message
			$this->setRedirect($router->route('index.php?view=login'), $e->getMessage(), 'error');
		}

		return true;
	}

	public function logout(): bool
	{
		$router  = $this->container->router;
		$manager = $this->container->userManager;
		$manager->logoutUser();

		$this->setRedirect($router->route('index.php?view=login'));

		return true;
	}

	protected function onBeforeDefault(): bool
	{
		$this->getView()->returnUrl = $this->getReturnUrl();

		return true;
	}

	private function getReturnUrl(): ?string
	{
		$url = $this->container->segment->getFlash('return_url');

		if ($url)
		{
			return $url;
		}

		$url = $this->input->getBase64('return', '');

		try
		{
			$url = base64_decode($url) ?: null;
		}
		catch (Throwable $e)
		{
			$url = null;
		}

		if (empty($url) || !Uri::isInternal($url))
		{
			return null;
		}

		return $url;
	}
}