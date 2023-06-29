<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class Login extends Controller
{
	public function login(): bool
	{
		/** @var LoggerInterface $logger */
		$logger = $this->container->loggerFactory->get('login');

		// Do not remove. This will throw an error if the database connection is broken.
		try
		{
			$this->container->db->setQuery('SELECT 1')->execute();
		}
		catch (Throwable $e)
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=login'),
				$e->getMessage(),
				'error'
			);
		}

		try
		{
			$this->csrfProtection();

			// Get the username and password from the request
			$username = $this->input->get('username', '', 'raw');
			$password = $this->input->get('password', '', 'raw');
			$secret   = $this->input->get('secret', '', 'raw');

			// Try to log in the user
			$manager = $this->container->userManager;
			$manager->loginUser($username, $password, ['secret' => $secret]);

			$logger->info('Successful login', ['username' => $username]);

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
			$logger->error('Failed login', [
				'username' => $username, 'error' => $e->getMessage(), 'source' => $e->getFile() . ':' . $e->getLine(),
			]);

			$router = $this->container->router;

			// Login failed. Go back to the login page and show the error message
			$this->setRedirect($router->route('index.php?view=login'), $e->getMessage(), 'error');
		}

		return true;
	}

	public function logout(): bool
	{
		/** @var LoggerInterface $logger */
		$logger   = $this->container->loggerFactory->get('login');
		$manager  = $this->container->userManager;
		$username = $manager->getUser()->getUsername();
		$router   = $this->container->router;

		$manager->logoutUser();
		$logger->info('Logged out', ['username' => $username]);

		$this->setRedirect($router->route('index.php?view=login'));

		return true;
	}

	protected function onBeforeDefault(): bool
	{
		$returnUrl                  = $this->getReturnUrl();
		$this->getView()->returnUrl = $returnUrl;

		if ($this->container->userManager->getUser()->getId() > 0)
		{
			$this->setRedirect($returnUrl ?: $this->container->router->route('index.php?view=main'), Text::_('PANOPTICON_APP_ERR_ALREADY_LOGGED_IN'), 'error');

			$this->redirect();

			return true;
		}

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
		catch (Throwable)
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