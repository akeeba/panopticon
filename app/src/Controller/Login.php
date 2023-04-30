<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;

class Login extends Controller
{
	public function login()
	{
		try
		{
			$this->csrfProtection();

			// Get the username and password from the request
			$username = $this->input->get('username', '', 'raw');
			$password = $this->input->get('password', '', 'raw');
			$secret = $this->input->get('secret', '', 'raw');

			// Try to log in the user
			$manager = $this->container->userManager;
			$manager->loginUser($username, $password, array('secret' => $secret));

			// Redirect to the saved return_url or, if none specified, to the application's main page
			$url = $this->container->segment->getFlash('return_url');
			$router = $this->container->router;

			if (empty($url))
			{
				$url = $router->route('index.php?view=main');
			}

			$this->setRedirect($url);
		}
		catch (\Exception $e)
		{
			$router = $this->container->router;

			// Login failed. Go back to the login page and show the error message
			$this->setRedirect($router->route('index.php?view=login'), $e->getMessage(), 'error');
		}

		return true;
	}

	public function logout()
	{
		$router = $this->container->router;
		$manager = $this->container->userManager;
		$manager->logoutUser();

		$this->setRedirect($router->route('index.php?view=main'));
	}
}