<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\UserAuthenticationPassword;
use Akeeba\Panopticon\Application\UserPrivileges;
use Akeeba\Panopticon\Library\MultiFactorAuth\MFATrait;
use Akeeba\Panopticon\Library\MultiFactorAuth\Plugin\PassKeys;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Application\Application as AWFApplication;
use Awf\Application\TransparentAuthentication;
use Awf\Document\Menu\Item;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\User\ManagerInterface;
use Awf\Utils\Ip;
use Exception;

class Application extends AWFApplication
{
	use MFATrait;

	/**
	 * List of view names we're allowed to access directly, without a login, and without redirection to the setup view
	 */
	private const NO_LOGIN_VIEWS = ['check', 'cron', 'login', 'setup'];

	private const MAIN_MENU = [
		[
			'view'        => 'main',
			'permissions' => [],
			'icon'        => 'fa fa-eye',
		],
		[
			'url'         => null,
			'permissions' => ['panopticon.super', 'panopticon.addown', 'panopticon.editown'],
			'name'        => 'administrator',
			'title'       => 'PANOPTICON_APP_MENU_TITLE_ADMINISTRATION',
			'icon'        => 'fa fa-screwdriver-wrench',
			'submenu'     => [
				[
					'view'        => 'sysconfig',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-gears',
				],
				[
					'url'         => null,
					'name'        => 'separator01',
					'title'       => '---',
					'permissions' => ['panopticon.super'],
				],
				[
					'view'        => 'sites',
					'permissions' => ['panopticon.admin', 'panopticon.addown', 'panopticon.editown'],
					'icon'        => 'fa fa-globe',
				],
				[
					'view'        => 'mailtemplates',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-envelope',
				],
				[
					'url'         => null,
					'name'        => 'separator02',
					'title'       => '---',
					'permissions' => ['panopticon.super'],
				],
				[
					'view'        => 'users',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-users',
				],
				[
					'view'        => 'groups',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-users-between-lines',
				],
				[
					'url'         => null,
					'name'        => 'separator03',
					'title'       => '---',
					'permissions' => ['panopticon.super'],
				],
				[
					'view'        => 'tasks',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-list-check',
				],
			],
		],
		[
			'url'          => null,
			'permissions'  => [],
			'name'         => 'user_submenu',
			//'icon'         => 'fa fa-user',
			'title'        => '',
			'titleHandler' => [self::class, 'getUserMenuTitle'],
			'submenu'      => [
				[
					'url'          => '#!disabled!',
					'name'         => 'user_username',
					'title'        => '',
					'permissions'  => [],
					'titleHandler' => [self::class, 'getUserNameTitle'],
				],
				[
					'url'         => null,
					'name'        => 'user_separator01',
					'title'       => '---',
					'permissions' => [],
				],
				[
					'view'        => 'user',
					'task'        => 'read',
					'title'       => 'PANOPTICON_USERS_TITLE_EDIT_MENU',
					'permissions' => [],
					'icon'        => 'fa fa-user-gear',
				],
				[
					'view'        => 'login',
					'task'        => 'logout',
					'title'       => 'PANOPTICON_APP_LBL_LOGOUT',
					'permissions' => [],
					'icon'        => 'fa fa-right-from-bracket',
				],
			],
		],
	];

	public static function getUserMenuTitle(): string
	{
		$user = Factory::getContainer()->userManager->getUser();

		$avatar = $user->getAvatar(64);

		return "<img src=\"$avatar\" alt=\"\" class=\"me-1\" style=\"width: 1.25em; border-radius: 0.625em \" >" .
			$user->getUsername();
	}

	public static function getUserNameTitle(): string
	{
		return sprintf(
			'<span class="small text-muted">%s</span>',
			Factory::getContainer()->userManager->getUser()->getName()
		);
	}

	public function initialise()
	{
		$this->getContainer()->html->run('grid.setJavascriptPrefix', 'akeeba.System.');

		$this->discoverSessionSavePath();
		$this->setTemplate('default');

		$this->registerMultifactorAuthentication();

		// Will I have to redirect to the setup page?
		$redirectToSetup = $this->redirectToSetup();

		/**
		 * DO NOT MOVE BELOW THE USER MANAGER INSTANTIATION.
		 *
		 * I need to tell the user manager to use a custom User class. The only way to do that is through the
		 * application configuration. Therefore, I need to load the application configuration, if it exists, before
		 * instantiating the user manager object.
		 */
		if (!$redirectToSetup)
		{
			$this->container->appConfig->loadConfiguration();
		}

		$this->loadLanguages();

		// Configure the user manager
		$this->container->appConfig->set('user_class', User::class);
		$manager = $this->container->userManager;
		$this->attachUserManagerPlugins($manager);

		if (!$redirectToSetup)
		{
			$this->container->session->setCsrfTokenAlgorithm(
				$this->container->appConfig->get('session_token_algorithm', 'sha512')
			);

			$this->applyTimezonePreference();
			$this->applySessionTimeout();

			if (!$this->needsMFA())
			{
				$this->conditionalRedirectToCronSetup();

				if (!$this->getMfaCheckedFlag() && $manager->getUser()->getId() > 0)
				{
					$this->setMfaCheckedFlag(true);
				}
			}
			else
			{
				$this->conditionalRedirectToCaptive();
			}
		}

		$this->loadRoutes();

		// Show the login page when necessary
		$this->redirectToLogin();

		// Set up the media query key
		$this->setupMediaVersioning();
	}

	public function dispatch()
	{
		parent::dispatch();

		// Initialise the main menu
		$this->initialiseMenu();
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

	private function initialiseMenu(array $items = self::MAIN_MENU, ?Item $parent = null): void
	{
		$menu  = $this->getDocument()->getMenu();
		$user  = $this->container->userManager->getUser();
		$order = 0;

		foreach ($items as $params)
		{
			$allowed = array_reduce(
				$params['permissions'] ?? [],
				fn(bool $carry, string $permission) => $carry && $user->getPrivilege($permission),
				true
			);

			if (!$allowed)
			{
				continue;
			}

			if (isset($params['permissions']))
			{
				unset($params['permissions']);
			}

			$order += 10;

			$options = [
				'show'         => $params['show'] ?? ['main'],
				'name'         => $params['name'] ?? $params['view'],
				'title'        => Text::_(
					$params['title'] ?? sprintf('%s_%s_TITLE', $this->getName(), $params['view'])
				),
				'order'        => $params['order'] ?? $order,
				'titleHandler' => $params['titleHandler'] ?? null,
				'icon'         => $params['icon'] ?? null,
			];

			if (isset($params['url']))
			{
				$options['url'] = $params['url'];
			}
			elseif (isset($params['view']))
			{
				$options['params'] = [
					'view' => $params['view'],
				];

				if (isset($params['task']))
				{
					$options['params']['task'] = $params['task'];
				}
			}
			elseif (isset($params['params']))
			{
				$options['params'] = $params['params'];
			}

			$item = new Item($options, $this->container);

			if ($parent !== null)
			{
				$parent->addChild($item);

				continue;
			}

			if ($params['submenu'] ?? null)
			{
				$this->initialiseMenu($params['submenu'], $item);
			}

			$menu->addItem($item);
		}
	}

	private function applySessionTimeout(): void
	{
		// Get the session timeout
		$sessionTimeout = (int) $this->container->appConfig->get('session_timeout', 1440);

		// Get the base URL and set the cookie path
		$uri = new Uri(Uri::base(false, $this->container));

		// Force the cookie timeout to coincide with the session timeout
		if ($sessionTimeout > 0)
		{
			$this->container->session->setCookieParams(
				[
					'lifetime' => $sessionTimeout * 60,
					'path'     => $uri->getPath(),
					'domain'   => $uri->getHost(),
					'secure'   => $uri->getScheme() === 'https',
					'httponly' => true,
				]
			);
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
			throw new Exception(
				'Your server does not provide any kind of hashing method. Please use a decent host.', 500
			);
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
		try
		{
			$defaultLanguage = $this->container->appConfig->get('language', 'en-GB');
		}
		catch (Exception $e)
		{
			$defaultLanguage = 'en-GB';
		}

		$detectedLanguage = Text::detectLanguage($this->container, '.ini', $this->container->languagePath);

		// Always load the English (Great Britain) language. It contains all the strings.
		Text::loadLanguage('en-GB', $this->container, '.ini', true, $this->container->languagePath);

		// Load the site's default language, if it's different from en-GB.
		if ($defaultLanguage != 'en-GB')
		{
			Text::loadLanguage($defaultLanguage, $this->container, '.ini', true, $this->container->languagePath);
		}

		// Load the auto-detected preferred language (per browser settings), as long as it's not one we already loaded.
		if (!in_array($detectedLanguage, [$defaultLanguage, 'en-GB']))
		{
			Text::loadLanguage($detectedLanguage, $this->container, '.ini', true, $this->container->languagePath);
		}
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

	private function attachUserManagerPlugins(ManagerInterface $manager): void
	{
		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);
		$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);
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

		$this->getContainer()->input->setData(
			[
				'view' => 'login',
			]
		);
	}

	private function setupMediaVersioning(): void
	{
		$this->getContainer()->mediaQueryKey = md5(microtime(false));
		$isDebug                             = !defined('AKEEBADEBUG');
		$isDevelopment                       = Version::getInstance()->isDev();

		if (!$isDebug && !$isDevelopment)
		{
			$this->getContainer()->mediaQueryKey = md5(
				__DIR__ . ':' . AKEEBA_PANOPTICON_VERSION . ':' . AKEEBA_PANOPTICON_DATE
			);
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

		$this->getContainer()->input->setData(
			[
				'view' => 'setup',
			]
		);

		return true;
	}

	private function conditionalRedirectToCronSetup(): void
	{
		// If we have finished the initial installation there's no need to redirect
		if ($this->container->appConfig->get('finished_setup', false))
		{
			return;
		}

		// Do not redirect if we're in a view which is allowed to be accessed directly (check, cron, login, setup)
		$view = $this->getContainer()->input->getCmd('view', '');

		if (in_array($view, self::NO_LOGIN_VIEWS))
		{
			return;
		}

		// Let the user finish the installation at their own time
		$this->redirect(Uri::rebase('index.php?view=setup&task=cron', $this->container));
	}

	private function conditionalRedirectToCaptive(): void
	{
		if (!$this->needsRedirectToCaptive())
		{
			return;
		}

		$captiveUrl = $this->container->router->route('index.php?view=captive');

		$this->redirect($captiveUrl);
	}

	private function registerMultifactorAuthentication()
	{
		$dispatcher = $this->container->eventDispatcher;

		foreach (
			[
				//FixedCodeDemo::class,
				PassKeys::class,
			] as $className
		)
		{
			$o = new $className($dispatcher);
		}
	}

}
