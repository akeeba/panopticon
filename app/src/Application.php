<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\UserAuthenticationGoogle;
use Akeeba\Panopticon\Application\UserAuthenticationPassword;
use Akeeba\Panopticon\Application\UserAuthenticationYubikey;
use Akeeba\Panopticon\Application\UserPrivileges;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Application\Application as AWFApplication;
use Awf\Application\TransparentAuthentication;
use Awf\Html\Grid;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\User\ManagerInterface;
use Awf\Utils\Ip;
use Exception;

class Application extends AWFApplication
{
	private const NO_LOGIN_VIEWS = ['check', 'login', 'setup'];

	public function initialise()
	{
		Grid::$javascriptPrefix = 'akeeba.System.';

		$this->discoverSessionSavePath();
		$this->setTemplate('default');
		$this->loadLanguages();


		if (!$this->redirectToSetup())
		{
			$this->container->appConfig->loadConfiguration();
			$this->applyTimezonePreference();
			$this->applySessionTimeout();
		}

		$this->loadRoutes();

		// Attach the user privileges to the user manager
		$manager = $this->container->userManager;

		$this->attachPrivileges($manager);

		// Only apply TFA when debug mode has not been enabled
		$this->applyTwoFactorAuthentication($manager);

		// Show the login page when necessary
		$this->redirectToLogin();

		// Set up the media query key
		$this->setupMediaVersioning();
	}

	public function createOrUpdateSessionPath(string $path, bool $silent = true): void
	{
		try
		{
			$fs            = $this->container->fileSystem;
			$protectFolder = false;

			if (!@is_dir($path))
			{
				$fs->mkdir($path, 0777);
			}
			elseif (!is_writeable($path))
			{
				$fs->chmod($path, 0777);
				$protectFolder = true;
			}
			else
			{
				if (!@file_exists($path . '/.htaccess'))
				{
					$protectFolder = true;
				}

				if (!@file_exists($path . '/web.config'))
				{
					$protectFolder = true;
				}
			}

			if ($protectFolder)
			{
				$fs->copy($this->container->basePath . '/.htaccess', $path . '/.htaccess');
				$fs->copy($this->container->basePath . '/web.config', $path . '/web.config');

				$fs->chmod($path . '/.htaccess', 0644);
				$fs->chmod($path . '/web.config', 0644);
			}
		}
		catch (Exception $e)
		{
			if (!$silent)
			{
				throw $e;
			}
		}
	}

	public function applySessionTimeout(): void
	{
		// Get the session timeout
		$sessionTimeout = (int) $this->container->appConfig->get('session_timeout', 1440);

		// Get the base URL and set the cookie path
		$uri = new Uri(Uri::base(false, $this->container));

		// Force the cookie timeout to coincide with the session timeout
		if ($sessionTimeout > 0)
		{
			$this->container->session->setCookieParams([
				'lifetime' => $sessionTimeout * 60,
				'path'     => $uri->getPath(),
				'domain'   => $uri->getHost(),
				'secure'   => $uri->getScheme() === 'https',
				'httponly' => true,
			]);
		}

		// Calculate a hash for the current user agent and IP address
		$ip         = Ip::getUserIP();
		$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$uniqueData = $ip . $userAgent . $this->container->basePath;
		$hash_algos = function_exists('hash_algos') ? hash_algos() : [];

		if (in_array('sha512', $hash_algos))
		{
			$sessionKey = hash('sha512', $uniqueData, false);
		}
		elseif (in_array('sha256', $hash_algos))
		{
			$sessionKey = hash('sha256', $uniqueData, false);
		}
		elseif (function_exists('sha1'))
		{
			$sessionKey = sha1($uniqueData);
		}
		elseif (function_exists('md5'))
		{
			$sessionKey = md5($uniqueData);
		}
		elseif (function_exists('crc32'))
		{
			$sessionKey = crc32($uniqueData);
		}
		elseif (function_exists('base64_encode'))
		{
			$sessionKey = base64_encode($uniqueData);
		}
		else
		{
			// ... put your server on a bed of thermite and light it with a magnesium flare!
			throw new Exception('Your server does not provide any kind of hashing method. Please use a decent host.', 500);
		}

		// Get the current session's key
		$currentSessionKey = $this->container->segment->get('session_key', '');

		// If there is no key, set it
		if (empty($currentSessionKey))
		{
			$this->container->segment->set('session_key', $sessionKey);
		}
		// If there is a key, and it doesn't match, trash the session and restart.
		elseif ($currentSessionKey != $sessionKey)
		{
			$this->container->session->destroy();
			$this->redirect($this->container->router->route('index.php'));
		}

		// If the session timeout is 0 or less than 0 there is no limit. Nothing to check.
		if ($sessionTimeout <= 0)
		{
			return;
		}

		// What is the last session timestamp?
		$lastCheck = $this->container->segment->get('session_timestamp', 0);
		$now       = time();

		// If there is a session timestamp make sure it's valid, otherwise trash the session and restart
		if (($lastCheck != 0) && (($now - $lastCheck) > ($sessionTimeout * 60)))
		{
			$this->container->session->destroy();
			$this->redirect($this->container->router->route('index.php'));
		}
		// In any other case, refresh the session timestamp
		else
		{
			$this->container->segment->set('session_timestamp', $now);
		}
	}

	private function discoverSessionSavePath(): void
	{
		$sessionPath = $this->container->session->getSavePath();

		if (!@is_dir($sessionPath) || !@is_writable($sessionPath))
		{
			$sessionPath = APATH_TMP . '/session';
			$this->createOrUpdateSessionPath($sessionPath);
			$this->container->session->setSavePath($sessionPath);
		}
	}

	private function loadLanguages(): void
	{
		Text::loadLanguage(null, 'panopticon', '.ini', false, $this->container->languagePath);
		Text::loadLanguage('en-GB', 'panopticon', '.ini', false, $this->container->languagePath);
	}

	private function applyTimezonePreference(): void
	{
		if (!function_exists('date_default_timezone_get') || !function_exists('date_default_timezone_set'))
		{
			return;
		}

		if (function_exists('error_reporting'))
		{
			$oldLevel = error_reporting(0);
		}

		$serverTimezone = @date_default_timezone_get();

		if (empty($serverTimezone) || !is_string($serverTimezone))
		{
			$serverTimezone = $this->container->appConfig->get('timezone', 'UTC');
		}

		if (function_exists('error_reporting'))
		{
			error_reporting($oldLevel ?? 0);
		}

		@date_default_timezone_set($serverTimezone);
	}

	private function loadRoutes(): void
	{
		$routesJSONPath = $this->container->basePath . '/assets/private/routes.json';
		$router         = $this->container->router;
		$importedRoutes = false;

		if (@file_exists($routesJSONPath))
		{
			$json = @file_get_contents($routesJSONPath);

			if (!empty($json))
			{
				$router->importRoutes($json);

				return;
			}
		}

		// If we could not import routes from routes.json, try loading routes.php
		$routesPHPPath = $this->container->basePath . '/assets/private/routes.php';

		if (@file_exists($routesPHPPath))
		{
			require_once $routesPHPPath;
		}
	}

	private function attachPrivileges(ManagerInterface $manager): void
	{
		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);
		$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);
	}

	private function applyTwoFactorAuthentication(ManagerInterface $manager): void
	{
		// Turn off TFA when debugging
		if (defined('AKEEBADEBUG'))
		{
			return;
		}

		$manager->registerAuthenticationPlugin('yubikey', UserAuthenticationYubikey::class);
		$manager->registerAuthenticationPlugin('google', UserAuthenticationGoogle::class);
	}

	private function redirectToLogin(): void
	{
		// Get the view. Necessary to go through $this->getContainer()->input as it may have already changed
		$view = $this->getContainer()->input->getCmd('view', '');

		// Get the user manager
		$manager = $this->container->userManager;

		/**
		 * Show the login page if there is no logged-in user, and we're not in the setup or login page already,
		 * and we're not using the remote (front-end backup), json (remote JSON API) views of the (S)FTP
		 * browser views (required by the session task of the setup view).
		 */
		if (in_array($view, self::NO_LOGIN_VIEWS) || $manager->getUser()->getId())
		{
			return;
		}

		// Try to perform transparent authentication
		$transparentAuth = new TransparentAuthentication($this->container);
		$credentials     = $transparentAuth->getTransparentAuthenticationCredentials();

		if (!is_null($credentials))
		{
			$this->container->segment->setFlash('auth_username', $credentials['username']);
			$this->container->segment->setFlash('auth_password', $credentials['password']);
			$this->container->segment->setFlash('auto_login', 1);
		}

		$return_url = $this->container->segment->getFlash('return_url');

		if (empty($return_url))
		{
			$return_url = Uri::getInstance()->toString();
		}

		$this->container->segment->setFlash('return_url', $return_url);

		$this->getContainer()->input->setData([
			'view' => 'login',
		]);
	}

	private function setupMediaVersioning(): void
	{
		$this->getContainer()->mediaQueryKey = md5(microtime(false));
		$isDebug                             = !defined('AKEEBADEBUG');
		$isDevelopment                       = Version::getInstance()->isDev();

		if (!$isDebug && !$isDevelopment)
		{
			$this->getContainer()->mediaQueryKey = md5(__DIR__ . ':' . AKEEBA_PANOPTICON_VERSION . ':' . AKEEBA_PANOPTICON_DATE);
		}
	}

	private function redirectToSetup(): bool
	{
		$configPath = $this->container->appConfig->getDefaultPath();

		if (
			@file_exists($configPath)
			|| in_array(
				$this->getContainer()->input->getCmd('view', ''),
				self::NO_LOGIN_VIEWS
			)
		)
		{
			return false;
		}

		$this->getContainer()->input->setData([
			'view' => 'setup',
		]);

		return true;
	}
}