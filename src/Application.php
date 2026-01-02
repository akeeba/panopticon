<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Akeeba\Panopticon\Library\MultiFactorAuth\MFATrait;
use Akeeba\Panopticon\Library\MultiFactorAuth\Plugin\PassKeys;
use Akeeba\Panopticon\Library\MultiFactorAuth\Plugin\TOTP;
use Akeeba\Panopticon\Library\Passkey\PasskeyTrait;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Application\Application as AWFApplication;
use Awf\Application\TransparentAuthentication;
use Awf\Document\Menu\Item;
use Awf\Uri\Uri;
use Awf\Utils\Ip;
use Exception;
use function array_map;

class Application extends AWFApplication
{
	use MFATrait;
	use PasskeyTrait;

	/**
	 * List of view names we're allowed to access directly, without a login, and without redirection to the setup view
	 */
	private const NO_LOGIN_VIEWS = ['check', 'cron', 'login', 'setup', 'passkeys'];

	/**
	 * Main menu structure
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private const MAIN_MENU = [
		[
			'url'         => null,
			'permissions' => [],
			'name'        => 'overview',
			'title'       => 'PANOPTICON_APP_MENU_TITLE_OVERVIEW',
			'icon'        => 'fa fa-fw fa-eye',
			'submenu'     => [
				[
					'view'        => 'main',
					'permissions' => [],
					'icon'        => 'fa fa-fw fa-globe',
				],
				[
					'view'        => 'extupdates',
					'permissions' => [],
					'icon'        => 'fa fa-fw fa-cubes',
				],
				[
					'view'        => 'coreupdates',
					'permissions' => [],
					'icon'        => 'fa fa-fw fa-atom',
				],
				[
					'url'   => null,
					'name'  => 'separator05',
					'title' => '---',
				],
				[
					'view'        => 'reports',
					'permissions' => [],
					'icon'        => 'fa fa-fw fa-table',
				],
			],
		],
		[
			'url'         => null,
			'permissions' => ['panopticon.super', 'panopticon.addown', 'panopticon.editown'],
			'name'        => 'administrator',
			'title'       => 'PANOPTICON_APP_MENU_TITLE_ADMINISTRATION',
			'icon'        => 'fa fa-fw fa-screwdriver-wrench',
			'submenu'     => [
				[
					'view'        => 'sysconfig',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-gears',
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
					'icon'        => 'fa fa-fw fa-globe',
				],
				[
					'view'        => 'mailtemplates',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-envelope',
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
					'icon'        => 'fa fa-fw fa-users',
				],
				[
					'view'        => 'groups',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-users-between-lines',
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
					'icon'        => 'fa fa-fw fa-list-check',
				],
				[
					'view'        => 'log',
					'title'       => 'PANOPTICON_LOGS_TITLE',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-file-lines',
				],
				[
					'url'         => null,
					'name'        => 'separator04',
					'title'       => '---',
					'permissions' => ['panopticon.super'],
				],
				[
					'view'        => 'selfupdate',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-cloud',
				],
				[
					'view'        => 'dbtools',
					'permissions' => ['panopticon.super'],
					'icon'        => 'fa fa-fw fa-database',
				],
			],
		],
		[
			'url'            => '#!hiddenTitle!',
			'permissions'    => [],
			'name'           => 'language_menu',
			'icon'           => 'fa fa-fw fa-language',
			'iconHandler'    => [self::class, 'getCurrentLanguageIcon'],
			'title'          => 'PANOPTICON_APP_LBL_LANGUAGE',
			'submenuHandler' => [self::class, 'getLanguageSubmenu'],
		],
		[
			'url'          => null,
			'permissions'  => [],
			'name'         => 'user_submenu',
			//'icon'         => 'fa fa-fw fa-user',
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
					'icon'        => 'fa fa-fw fa-user-gear',
				],
				[
					'view'        => 'login',
					'task'        => 'logout',
					'title'       => 'PANOPTICON_APP_LBL_LOGOUT',
					'permissions' => [],
					'icon'        => 'fa fa-fw fa-right-from-bracket',
				],
			],
		],
	];

	/**
	 * Preload resources for HTTP 103 early hints.
	 *
	 * Format: relative path => preload type.
	 *
	 * @var    array
	 * @since  1.1.0
	 * @see    https://developer.chrome.com/docs/web-platform/early-hints
	 * @see    https://developer.mozilla.org/en-US/docs/Web/HTML/Element/link#as
	 */
	private const HINT_PRELOAD = [
		'media/css/theme.min.css?_MEDIAKEY_=1'      => 'style',
		'media/css/fontawesome.css?_MEDIAKEY_=1'    => 'style',
		//'media/images/logo_bw.svg'               => 'image',
		//'media/images/logo_colour.svg'           => 'image',
		'media/js/darkmode.min.js'                  => 'script',
		'media/js/system.js?_MEDIAKEY_=1'           => 'script',
		'media/js/bootstrap.bundle.js?_MEDIAKEY_=1' => 'script',
		'media/webfonts/fa-solid-900.woff2'         => 'font',
		'media/webfonts/fa-brands-400.woff2'        => 'font',
		'media/webfonts/fa-regular-400.woff2'       => 'font',
	];

	public static function getUserMenuTitle(): string
	{
		$container = Factory::getContainer();
		$hasAvatar = $container->appConfig->get('avatars', false);
		$user      = $container->userManager->getUser();

		if (!$hasAvatar)
		{
			return '<span class="fa fa-fw fa-user me-1" aria-hidden="true"></span>' . $user->getUsername();
		}

		$avatar = $user->getAvatar(64);

		return "<img src=\"$avatar\" alt=\"\" class=\"me-1\" style=\"width: 1.25em; border-radius: 0.625em \" >"
		       . $user->getUsername();
	}

	public static function getUserNameTitle(): string
	{
		return sprintf(
			'<span class="small text-muted">%s</span>', Factory::getContainer()->userManager->getUser()->getName()
		);
	}

	public static function getCurrentLanguageIcon(): string
	{
		$languages       = Factory::getContainer()->helper->setup->getLanguageOptions(false);
		$currentLanguage = self::getCurrentLanguage();

		if (!empty($currentLanguage) && isset($languages[$currentLanguage]))
		{
			$langName = $languages[$currentLanguage];
			[$icon,] = explode('&nbsp;', $langName);

			return $icon;
		}

		return 'fa fa-fw fa-language';
	}

	public static function getLanguageSubmenu(): array
	{
		$languages       = Factory::getContainer()->helper->setup->getLanguageOptions(false);
		$items           = [];
		$currentLanguage = self::getCurrentLanguage();
		$langUrl         = fn($langCode) => Factory::getContainer()->router->route(
			sprintf(
				'index.php?view=main&task=switchLanguage&lang=%s&returnurl=%s',
				$langCode,
				base64_encode(Uri::getInstance()->toString())
			)
		);

		// Add an icon for the default language
		$items[] = [
			'url'         => $langUrl(''),
			'permissions' => [],
			'name'        => 'set_lang_default',
			'icon'        => 'fa fa-fw fa-language',
			'title'       => 'PANOPTICON_USERS_LBL_FIELD_FIELD_LANGUAGE_AUTO',
		];

		// Push the current language up top
		if (!empty($currentLanguage) && isset($languages[$currentLanguage]))
		{
			$langName = $languages[$currentLanguage];
			unset($languages[$currentLanguage]);

			$items[] = [
				'url'         => $langUrl($currentLanguage) . '#!disabled!',
				'permissions' => [],
				'name'        => 'set_lang_' . $currentLanguage,
				'title'       => $langName,
			];
		}

		// Add divider
		$items[] = [
			'url'         => null,
			'name'        => 'lang_separator01',
			'title'       => '---',
			'permissions' => [],
		];

		// Sort the other languages
		uasort(
			$languages, function ($a, $b) {
			[, $nameA] = explode('&nbsp;', $a);
			[, $nameB] = explode('&nbsp;', $b);

			return $nameA <=> $nameB;
		}
		);

		// Add submenu items
		foreach ($languages as $code => $langName)
		{
			$items[] = [
				'url'         => $langUrl($code),
				'permissions' => [],
				'name'        => 'set_lang_' . $code,
				'title'       => '<span lang="' . $code . '">' . $langName . '</span>',
			];
		}

		return $items;
	}

	private static function getCurrentLanguage(bool $fallbackUser = false, bool $fallbackApp = false): string
	{
		$container      = Factory::getContainer();
		$appLanguage    = $container->appConfig->get('language', 'en-GB');
		$forcedLanguage = $container->segment->get('panopticon.forced_language', null);
		$userLanguage   = $container->userManager->getUser()->getParameters()->get('language');

		if ($forcedLanguage)
		{
			return $forcedLanguage;
		}

		if (!empty($userLanguage) && $fallbackUser)
		{
			return $userLanguage;
		}

		return $fallbackApp ? $appLanguage : '';
	}

	public function initialise()
	{
		// Set up the media query key
		$this->setupMediaVersioning();

		// HTTP 103 early hints
		$this->preloadHints();

		// Set up the session
		$this->container->session->start();

		// Apply a forced language – but only if there is no logged-in user, or they have no language preference.
		$forcedLanguage = $this->getContainer()->segment->get('panopticon.forced_language', null);

		if ($forcedLanguage)
		{
			$this->getLanguage()->loadLanguage($forcedLanguage);
		}
		elseif ($this->getContainer()->userManager->getUser()->getId() > 0)
		{
			$this->getLanguage()->loadLanguage(
				$this->getLanguage()->detectLanguage(
					null,
					$this->getContainer()->userManager->getUser()
				)
			);
		}

		// Will I have to redirect to the setup page?
		$redirectToSetup = $this->redirectToSetup();

		// Set up the Grid JS prefix
		$this->getContainer()->html->grid->setJavascriptPrefix('akeeba.System.');

		// Initialisation
		$this->setTemplate('default');
		$this->registerMultifactorAuthentication();

		// Apply the custom template, if one is defined
		$this->applyCustomTemplate();

		if (!$redirectToSetup)
		{
			$this->container->session->setCsrfTokenAlgorithm(
				$this->container->appConfig->get('session_token_algorithm', 'sha512')
			);

			$this->applyTimezonePreference();
			$this->applySessionTimeout();

			if (!$this->needsMFA())
			{
				$this->conditionalRedirectToCaptiveSetup();
				$this->conditionalRedirectToPasskeySetup();
				$this->conditionalRedirectToCronSetup();

				if (
					!$this->getMfaCheckedFlag()
					&& $this->getContainer()->userManager->getUser()->getId() > 0
				)
				{
					$this->setMfaCheckedFlag(true);
				}
			}
			else
			{
				$this->conditionalRedirectToCaptive();
			}
		}

		// Load routing information (reserved for future use)
		$this->loadRoutes();

		// Show the login page when necessary
		$this->redirectToLogin();
	}

	public function dispatch()
	{
		parent::dispatch();

		// Initialise the main menu
		$this->initialiseMenu();
	}

	private function initialiseMenu(?array $items = null, ?Item $parent = null): void
	{
		$menu  = $this->getDocument()->getMenu();
		$user  = $this->container->userManager->getUser();
		$order = 0;

		$items ??= array_map([$this, 'applyMenuItemHandlers'], self::MAIN_MENU);

		foreach ($items as $params)
		{
			$allowed = array_reduce(
				$params['permissions'] ?? [],
				fn(bool $carry, string $permission) => $carry && $user->getPrivilege($permission), true
			);

			// Do not show the System Configuration or its separator if we're using .env files
			if (
				(($params['view'] ?? null) === 'sysconfig' || ($params['name'] ?? '') === 'separator01')
				&& BootstrapUtilities::hasConfiguration(true)
			)
			{
				$allowed = false;
			}

			if (!$allowed)
			{
				continue;
			}

			if (isset($params['permissions']))
			{
				unset($params['permissions']);
			}

			$order += 10;

			$title = $params['title'] ?? sprintf('%s_%s_TITLE', $this->getName(), $params['view']);

			if (str_starts_with(strtoupper($title), 'PANOPTICON_'))
			{
				$title = $this->getLanguage()->text($title);
			}

			$options = [
				'show'         => $params['show'] ?? ['main'],
				'name'         => $params['name'] ?? $params['view'],
				'title'        => $title,
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

	private function applyMenuItemHandlers(array $item): array
	{
		if (isset($item['iconHandler']))
		{
			$item['icon'] = is_callable($item['iconHandler'])
				? call_user_func($item['iconHandler'])
				:
				($item['icon'] ?? '');

			unset($item['iconHandler']);
		}

		if (isset($item['submenuHandler']))
		{
			$item['submenu'] = is_callable($item['submenuHandler'])
				? call_user_func($item['submenuHandler'])
				:
				($item['submenu'] ?? '');

			unset($item['submenuHandler']);
		}

		if (isset($item['submenu']) && is_array($item['submenu']) && !empty($item['submenu']))
		{
			$item['submenu'] = array_map([$this, 'applyMenuItemHandlers'], $item['submenu']);
		}

		return $item;
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
			$sessionKey = hash('sha1', $uniqueData, false);
		}
		elseif (function_exists('md5'))
		{
			$sessionKey = hash('md5', $uniqueData, false);
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

	private function redirectToLogin(): void
	{
		// Get the view. Necessary to go through $this->getContainer()->input as it may have already changed.
		$view = $this->getContainer()->input->getCmd('view', '');
		$task = $this->getContainer()->input->getCmd('task', '');

		// Get the user manager
		$manager = $this->container->userManager;

		if ($view === 'login')
		{
			$lang = $this->getContainer()->input->getCmd('lang', null);

			if ($lang !== null)
			{
				$this->getContainer()->segment->set('panopticon.forced_language', $lang);

				$this->getLanguage()->loadLanguage($lang ?: 'en-GB');
			}
		}

		/**
		 * Special case: password reset
		 */
		if ($view === 'users' && in_array($task, ['pwreset', 'confirmreset']))
		{
			return;
		}

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
		$this->getContainer()->mediaQueryKey = hash('md5', microtime(false));
		$isDebug                             = !defined('AKEEBADEBUG');
		$isDevelopment                       = Version::getInstance()->isDev();

		if (!$isDebug && !$isDevelopment)
		{
			$this->getContainer()->mediaQueryKey = hash(
				'md5',
				__DIR__ . ':' . AKEEBA_PANOPTICON_VERSION . ':' . AKEEBA_PANOPTICON_DATE
			);
		}
	}

	private function redirectToSetup(): bool
	{
		if (BootstrapUtilities::hasConfiguration()
		    || in_array(
			    $this->getContainer()->input->getCmd('view', ''), self::NO_LOGIN_VIEWS
		    ))
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
		$task = $this->getContainer()->input->getCmd('task', '');

		/**
		 * Special case: password reset
		 */
		if ($view === 'users' && in_array($task, ['pwreset', 'confirmreset']))
		{
			return;
		}

		// Other views
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

	private function conditionalRedirectToCaptiveSetup(): void
	{
		if (!$this->needsMFAForcedSetup())
		{
			return;
		}

		$user       = $this->getContainer()->userManager->getUser();
		$captiveUrl = $this->getContainer()->router->route(
			sprintf(
				"index.php?view=users&task=edit&id=%s&collapseForMFA=1",
				$user->getId()
			)
		);

		$this->redirect($captiveUrl);
	}

	private function conditionalRedirectToPasskeySetup(): void
	{
		if (!$this->needsPasskeyForcedSetup())
		{
			return;
		}

		$user       = $this->getContainer()->userManager->getUser();
		$captiveUrl = $this->getContainer()->router->route(
			sprintf(
				"index.php?view=users&task=edit&id=%s&collapseForPasskey=1",
				$user->getId()
			)
		);

		$this->redirect($captiveUrl);
	}

	private function registerMultifactorAuthentication()
	{
		$dispatcher = $this->container->eventDispatcher;

		foreach (
			[
				//Akeeba\Panopticon\Library\MultiFactorAuth\Plugin\FixedCodeDemo::class,
				PassKeys::class,
				TOTP::class,
			] as $className
		)
		{
			$o = new $className($dispatcher, $this->getContainer(), $this->getLanguage());
		}
	}

	/**
	 * Apply a custom template
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	private function applyCustomTemplate(): void
	{
		$customTemplate = $this->container->appConfig->get('template', 'default');

		if (!empty($customTemplate))
		{
			$this->setTemplate($customTemplate);

			if (empty($this->getTemplate()) || $this->getTemplate() === 'Panopticon')
			{
				$this->setTemplate('default');
			}
		}
	}

	/**
	 * Improves the page load time by sending preload hints.
	 *
	 * On regular servers, e.g. FastCGI, these are sent _with_ the request response, just as if we had them as <link>
	 * elements in the HTML output.
	 *
	 * On FrankenPHP we send them as HTTP 103 responses. These are sent **BEFORE** the request response, which improves
	 * performance.
	 *
	 * @return  void
	 * @since   1.1.0
	 * @see     https://developer.chrome.com/docs/web-platform/early-hints
	 * @see     https://speakerdeck.com/dunglas/webperf-boost-your-php-apps-with-103-early-hints
	 */
	private function preloadHints()
	{
		$hintsOutput = 0;
		$container   = $this->getContainer();
		$input       = $container->input;

		// We must only send preload hints in HTML output.
		if (($input->getCmd('format', 'html') ?: 'html') !== 'html')
		{
			return;
		}

		$mediaKey = $container->mediaQueryKey;
		$basePath = $container->filesystemBase;
		$relUri   = Uri::base(true, $container);
		$relUri   .= !str_ends_with($relUri, '/') ? '/' : '';

		foreach (self::HINT_PRELOAD as $file => $as)
		{
			$parts     = explode('?', $file);
			$nakedFile = reset($parts);

			if (!file_exists($basePath . '/' . $nakedFile))
			{
				continue;
			}

			if (
				!str_contains($file, '.min.') && !(defined('AKEEBADEBUG') && AKEEBADEBUG)
				&& (
					str_ends_with($file, '.js') || str_ends_with($file, '.css')
					|| str_contains($file, '.js?')
					|| str_contains($file, '.css?')
				)
			)
			{
				$lastDot = strrpos($file, '.');
				$altFile = substr($file, 0, $lastDot) . '.min' . substr($file, $lastDot);

				$parts     = explode('?', $file);
				$nakedFile = reset($parts);

				if (file_exists($basePath . '/' . $nakedFile))
				{
					$file = $altFile;
				}
			}

			// Replace _MEDIAKEY_
			$file  = str_replace('_MEDIAKEY_', $mediaKey, $file);
			$extra = '';

			if ($as === 'font')
			{
				$extra = '; crossorigin';
			}

			$hintsOutput++;
			header(sprintf("Link: <%s%s>; rel=preload; as=%s%s", $relUri, $file, $as, $extra), false);
		}

		// This is required for FrankenPHP, see https://frankenphp.dev/docs/early-hints/
		if ($hintsOutput && function_exists('headers_send'))
		{
			try
			{
				headers_send(103);
			}
			catch (\Throwable)
			{
				// Nada
			}
		}
	}

}
