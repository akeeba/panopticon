<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application;

use AConfig;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Loginfailures;
use Awf\Registry\Registry;
use Awf\Utils\Buffer;
use Awf\Utils\Ip;
use Composer\CaBundle\CaBundle;
use Dotenv\Dotenv;
use ReflectionException;
use ReflectionObject;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Throwable;

defined('AKEEBA') || die;

/**
 * Common code for the application bootstrapping process.
 *
 * This code used to live in whole inside bootstrap.php. Putting it in a final class allows us to keep it more tidy, and
 * prevent all unnecessary variable pollution of the root namespace.
 *
 * @since  1.0.3
 */
final class BootstrapUtilities
{
	/**
	 * Used to keep the tmp_file() of the combined CA file in scope until the end of the request.
	 *
	 * @var   false|resource
	 * @since 1.0.3
	 */
	static $tempCaCert = false;

	private static $secret = null;

	/**
	 * Asserts that the server meets the minimum PHP version requirement
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function assertMinimumPHPVersion(): void
	{
		if (!version_compare(
			PHP_VERSION, defined('AKEEBA_PANOPTICON_MINPHP') ? AKEEBA_PANOPTICON_MINPHP : '8.1.0', 'lt'
		))
		{
			return;
		}

		if (defined('AKEEBA_PANOPTICON_CLI') || !@is_file(APATH_THEMES . '/system/incompatible.html'))
		{
			echo sprintf(
				"Akeeba Panopticon requires PHP %s or later. Your server is using PHP %s." . PHP_EOL,
				AKEEBA_PANOPTICON_MINPHP, PHP_VERSION
			);

			exit(254);
		}

		die(
		str_replace(
			['{{minphpversion}}', '{{phpversion}}'], [AKEEBA_PANOPTICON_MINPHP, PHP_VERSION],
			file_get_contents(APATH_THEMES . '/system/incompatible.html')
		)
		);

	}

	/**
	 * Asserts that we are not running under HHVM.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function assertNotHHVM(): void
	{
		if (!defined('HHVM_VERSION'))
		{
			return;
		}

		if (defined('AKEEBA_PANOPTICON_CLI') || !@is_file(APATH_THEMES . '/system/hhvm.html'))
		{
			echo "Akeeba Panopticon is not compatible with HHVM. Please use PHP proper." . PHP_EOL;

			exit(254);
		}

		die(file_get_contents(APATH_THEMES . '/system/hhvm.html'));
	}

	/**
	 * Asserts that the Composer dependencies have been installed
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function assertComposerInstalled(): void
	{
		if (file_exists(APATH_ROOT . '/vendor/autoload.php') && @is_file(APATH_THEMES . '/system/incomplete.html'))
		{
			return;
		}

		if (defined('AKEEBA_PANOPTICON_CLI'))
		{
			echo "Akeeba Panopticon has not been installed correctly." . PHP_EOL;

			exit(254);
		}

		die(file_get_contents(APATH_THEMES . '/system/incomplete.html'));
	}

	/**
	 * Apply our customised Symfony error handler as the default exceptions handler
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function applyExceptionsHandler(): void
	{
		// Override Symfony's error handler
		BootstrapUtilities::overrideHtmlErrorRenderer();

		// Create an error handler
		$errorHandler = ErrorHandler::register();

		// Set our custom error handler theme
		HtmlErrorRenderer::setTemplate(APATH_THEMES . '/system/fatal.php');

		// Don't throw for trivial warnings and notices
		$errorHandler->throwAt(0, true);

		/**
		 * Set up maximum error handling in the following conditions:
		 *
		 * - No configuration file is present (initial setup)
		 * - The AKEEBADEBUG constant is set and is true
		 * - The debug configuration option is enabled
		 * - The error_reporting configuration option is set to `maximum`
		 */
		if (
			!self::hasConfiguration()
			|| (defined('AKEEBADEBUG') && constant('AKEEBADEBUG'))
			|| self::getInitialConfiguration()->get('debug', false)
			|| self::getInitialConfiguration()->get('error_reporting', 'default') !== 'maximum'
		)
		{
			$errorHandler->setExceptionHandler(
				[
					new ErrorHandler(null, true),
					'renderException',
				]
			);
		}
	}

	/**
	 * Do we have any valid application configuration file?
	 *
	 * @param   bool  $dotEnvOnly  Should I only consider .env files?
	 *
	 * @return  bool
	 * @since   1.0.3
	 */
	public static function hasConfiguration(bool $dotEnvOnly = false): bool
	{
		$environment = $_SERVER['PANOPTICON_ENVIRONMENT'] ?? $_ENV['PANOPTICON_ENVIRONMENT'] ?? 'production';

		$filePaths = [
			APATH_CONFIGURATION . '/config.php',
			APATH_CONFIGURATION . '/.env',
			APATH_CONFIGURATION . '/.env.' . $environment,
			APATH_USER_CODE . '/.env',
			APATH_USER_CODE . '/.env.' . $environment,
		];

		if ($dotEnvOnly)
		{
			$filePaths = array_slice($filePaths, 1);
		}

		return array_reduce(
			$filePaths,
			fn(bool $carry, string $filePath) => $carry || (file_exists($filePath) && is_readable($filePath)), false
		);
	}

	/**
	 * Apply the error_reporting configuration preference to PHP itself.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function applyErrorReportingToPHP(): void
	{
		// Load the configuration; we'll need it to set up error reporting and handling
		$config = self::getInitialConfiguration();

		// Set the PHP error reporting
		switch ($config->get('error_reporting', 'default'))
		{
			default:
				break;

			case 'none':
				error_reporting(0);
				ini_set('display_errors', 0);

				break;

			case 'simple':
				error_reporting(E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING);
				ini_set('display_errors', 1);

				break;

			case 'maximum':
				error_reporting(E_ALL);
				ini_set('display_errors', 1);

				break;
		}
	}

	/**
	 * Apply the Debug preference as a PHP constant (if not already defined)
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function applyDebugToConstant(): void
	{
		// Load the configuration; we'll need it to set up error reporting and handling
		$config = self::getInitialConfiguration();

		// Do we need to set up a more detailed error handler?
		if (!defined('AKEEBADEBUG'))
		{
			define('AKEEBADEBUG', $config->get('debug', false));
		}
	}

	/**
	 * Apply the Behind Load Balancer preference to AWF's Ip helper
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function applyLoadBalancerConfiguration(): void
	{
		Ip::setAllowIpOverrides(self::getInitialConfiguration()->get('behind_load_balancer', false));
	}

	/**
	 * Apply a custom CA file.
	 *
	 * If the user has not provided a custom file we just use the cacert.pem provided by Composer.
	 *
	 * Otherwise, we combine the user– and the Composer–provided files in a temporary file which is kept in scope
	 * throughout the request.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function applyCustomCAFile(): void
	{
		if (defined('AKEEBA_CACERT_PEM'))
		{
			return;
		}

		$defaultCacertPath  = CaBundle::getBundledCaBundlePath();
		$customFile         = APATH_USER_CODE . '/cacert.pem';
		$customFileContents = (file_exists($customFile) && is_readable($customFile)) ? @file_get_contents($customFile) : null;
		$customFileContents = $customFileContents ?: null;

		if ($customFileContents === null)
		{
			define('AKEEBA_CACERT_PEM', $defaultCacertPath);

			return;
		}

		$cacertContents = file_get_contents($defaultCacertPath);
		self::$tempCaCert = tmpfile();
		$tempCaCertPath = stream_get_meta_data(self::$tempCaCert)['uri'];
		fwrite(self::$tempCaCert, $cacertContents);
		fwrite(self::$tempCaCert,"\n\n");
		fwrite(self::$tempCaCert,$customFileContents);

		define('AKEEBA_CACERT_PEM', $tempCaCertPath);
	}

	/**
	 * Load the user–provided bootstrap.php file, if one exists.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public static function loadUserCode(): void
	{
		if (!file_exists(APATH_USER_CODE . '/bootstrap.php'))
		{
			return;
		}

		require_once APATH_USER_CODE . '/bootstrap.php';
	}

	/**
	 * Force Blade templates' recompilation when debug mode is enabled.
	 *
	 * @return  void
	 * @throws  ReflectionException
	 * @since   1.0.3
	 */
	public static function conditionallyForceBladeRecompilation(): void
	{
		if (!AKEEBADEBUG && !self::getInitialConfiguration()->get('debug', false))
		{
			return;
		}

		$refBlade = new ReflectionObject(Factory::getContainer()->blade);
		$refProp  = $refBlade->getProperty('isCacheable');
		/** @noinspection PhpExpressionResultUnusedInspection */
		$refProp->setAccessible(true);
		$refProp->setValue(Factory::getContainer()->blade, false);
	}

	/**
	 * Make sure we have a secret key set up for this installation.
	 *
	 * If the application is not configured yet, the secret is stored in tmp/secret.php.
	 *
	 * If the application is configured, we check if we need to transcribe the secret to the config.php file. In case a
	 * .env file is being used we cannot write the secret. In this case, we just transcribe the secret to the runtime
	 * application configuration. The secret will be stored in the tmp/secret.php file.
	 *
	 * @return void
	 * @throws \Random\RandomException
	 */
	public static function applySecret()
	{
		// Do I already have a secret, e.g. through user code?
		if (self::$secret)
		{
			return;
		}

		// Do I have a configured secret?
		$secret = trim(self::getInitialConfiguration()->get('secret', null) ?? '');

		if (!empty($secret))
		{
			return;
		}

		// Do I have a temporary file defining a secret?
		if (file_exists(APATH_TMP . '/secret.php'))
		{
			require_once APATH_TMP . '/secret.php';
		}

		if (defined('AKEEBA_SECRET'))
		{
			return;
		}

		// Create a new secret and save it into a temporary file.
		$randomStringGenerator = function(int $length): string {
			$allCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			$baseLength    = \strlen($allCharacters);
			$outString     = '';
			$sourceBytes   = random_bytes($length + 1);
			$baseShift     = \ord($sourceBytes[0]);

			for ($i = 1; $i <= $length; ++$i)
			{
				$outString .= $allCharacters[($baseShift + \ord($sourceBytes[$i])) % $baseLength];
				$baseShift += \ord($sourceBytes[$i]);
			}

			return $outString;
		};

		$secret       = $randomStringGenerator(64);
		self::$secret = $secret;

		$fileContent = <<< PHP
<?php
defined('AKEEBA') or die;
if (!defined('AKEEBA_SECRET')) {
	define('AKEEBA_SECRET', '{$secret}');
}

PHP;

		file_put_contents(APATH_TMP . '/secret.php', $fileContent);
	}

	/**
	 * Loads the application configuration, if it exists.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	public static function loadConfiguration(): void
	{
		// Only try to load the configuration file if we have one to begin with.
		if (!self::hasConfiguration())
		{
			return;
		}

		$appConfig = Factory::getContainer()->appConfig;
		$appConfig->loadConfiguration();

		// Transfer the secret to the application configuration if necessary
		$deleteOld = !empty(self::$secret);

		if (self::$secret && !$appConfig->get('secret'))
		{
			$appConfig->set('secret', self::$secret);

			if ($appConfig->isReadWrite())
			{
				try
				{
					$appConfig->saveConfiguration();
				}
				catch (\Exception $e)
				{
					$deleteOld = false;
				}
			}
		}

		if ($deleteOld && @file_exists(APATH_TMP . '/secret.php'))
		{
			@unlink(APATH_TMP . '/secret.php');
		}
	}

	/**
	 * Set up the User Manager, the user privileges, and the user authentication.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	public static function setUpUserManager(): void
	{
		$container = Factory::getContainer();

		$container->appConfig->set('user_class', User::class);
		$manager = $container->userManager;

		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);
		$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);
	}

	/**
	 * Make sure the fallback language (en-GB) is always loaded
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public static function fallbackLanguage()
	{
		$language = Factory::getContainer()->language;
		$langCode = $language->getLangCode();

		if ($langCode === 'en-GB')
		{
			return;
		}

		$language->loadLanguage('en-GB');
		$language->loadLanguage($langCode);
	}

	public static function mySQLUseGMT()
	{
		try
		{
			Factory::getContainer()
				->db
				->setQuery("SET @@session.time_zone = '+0:00'")
				->execute();
		}
		catch (Throwable $e)
		{
			// Ignore it if this fails.
		}
	}

	public static function evaluateIPBlocking()
	{
		/** @var Loginfailures $loginfailures */
		$loginfailures = Factory::getContainer()->mvcFactory->makeModel('Loginfailures');

		try
		{
			$isIPBlocked = $loginfailures->isIPBlocked();
		}
		catch (\Exception $e)
		{
			return;
		}

		if (!$isIPBlocked)
		{
			return;
		}

			header('HTTP/1.0 403 Forbidden');

		@include APATH_THEMES . '/system/forbidden.html.php';

		exit();
	}

	/**
	 * Addresses MySQL errors about the sort buffer being too short, especially when sending email..
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	public static function workaroundMySQLSortBufferSize(): void
	{
		try
		{
			Factory::getContainer()
				->db
				->setQuery('SET sort_buffer_size = 256000000')
				->execute();
		}
		catch (Throwable $e)
		{
			// Ignore it if this fails.
		}
	}

	/**
	 * Overrides the Symfony HtmlErrorRenderer for customisation reasons.
	 *
	 * Overrides the Symfony HtmlErrorRenderer:
	 *  - Always give detailed information, even to the "simple" error page
	 *  - Allow overriding the debug template
	 *
	 * Why this in-memory patching trickery instead of overriding the class, you ask? There's a good reason!
	 *
	 * We need to override two private methods. Overriding a private method in a descendant class doesn't work (the
	 * parent class' code will still use the parent class' private member instead of the one we defined in the
	 * descendant). This means that we'd have to copy the entire class instead of extending from it. While possible, it
	 * makes it far harder to update the code several months or years later when the overridden class breaks. Using
	 * in-memory patching we can readily see the handful of lines we changed, making it easy to update.
	 *
	 * Kids, don't try this at home. We are trained professionals with over two decades of experience doing weird things
	 * in PHP code.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private static function overrideHtmlErrorRenderer(): void
	{
		// Loads the buffer class and registers the `awf://` stream handler.
		class_exists(Buffer::class);

		// Override FlattenException
		$sourceCode = @file_get_contents(APATH_BASE . '/vendor/symfony/error-handler/Exception/FlattenException.php');

		$sourceCode = str_replace(
			'$statusCode = 500;', <<< PHP
\$statusCode = in_array(\$exception->getCode(), [400, 401, 403, 404, 406, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 425, 428, 429, 431, 451, 500, 501, 502, 503, 504, 505, 506, 510, 511]) ? \$exception->getCode() : 500;
PHP, $sourceCode
		);
		$sourceCode = str_replace(
			'$statusText = \'Whoops, looks like something went wrong.\';', '$statusText = $exception->getMessage();',
			$sourceCode
		);


		$tempFile = 'awf://tmp/FlattenException.php';
		file_put_contents($tempFile, $sourceCode);
		require_once $tempFile;
		@unlink($tempFile);

		// Override HtmlErrorRenderer
		$sourceCode = @file_get_contents(
			APATH_BASE . '/vendor/symfony/error-handler/ErrorRenderer/HtmlErrorRenderer.php'
		);

		//$sourceCode = str_replace('if (!$debug) {', 'if (true) {', $sourceCode);
		$sourceCode = str_replace(
			'return $this->include(self::$template, [', <<<PHP
return \$this->include(self::\$template, [
            'exception' => \$exception,
            'exceptionMessage' => \$this->escape(\$exception->getMessage()),
            'logger' => \$this->logger instanceof DebugLoggerInterface ? \$this->logger : null,
            'currentContent' => \is_string(\$this->outputBuffer) ? \$this->outputBuffer : (\$this->outputBuffer)(),

PHP, $sourceCode
		);
		$sourceCode = str_replace(
			'include is_file(\dirname(__DIR__).\'/Resources/\'.$name) ? \dirname(__DIR__).\'/Resources/\'.$name : $name;', <<<PHP
	include array_reduce([
		APATH_THEMES . '/system/' . str_replace('views/', 'error/', \$name),
		APATH_BASE . '/vendor/symfony/error-handler/Resources/' . \$name,
		\$name
	], fn(\$carry, \$path) => \$carry ?? (file_exists(\$path) ? \$path : null), null);

PHP, $sourceCode
		);

		$tempFile = 'awf://tmp/HtmlErrorRenderer.php';
		file_put_contents($tempFile, $sourceCode);
		require_once $tempFile;
		@unlink($tempFile);
	}

	/**
	 * Get a Registry object with the configuration variables provided in the best matched config file.
	 *
	 * @return  Registry
	 * @since   1.0.3
	 */
	private static function getInitialConfiguration(): Registry
	{
		static $registry = null;

		if (!is_null($registry))
		{
			return $registry;
		}

		$registry = new Registry();

		if (!self::hasConfiguration())
		{
			return $registry;
		}

		if (self::hasConfiguration(true))
		{
			$environment = $_SERVER['PANOPTICON_ENVIRONMENT'] ?? $_ENV['PANOPTICON_ENVIRONMENT'] ?? 'production';
			$dotEnv      = Dotenv::createArrayBacked(
				[
					APATH_CONFIGURATION,
					APATH_USER_CODE,
				], [
					'/.env',
					'/.env.' . $environment,
				]
			);
			$loaded      = $dotEnv->safeLoad();

			$loaded = array_combine(
				array_map(
					fn($x) => strtolower(substr($x, 11)), array_keys($loaded)
				), array_values($loaded)
			);

			$registry->loadArray($loaded);
		}
		else
		{
			ob_start();
			require_once APATH_CONFIGURATION . '/config.php';
			ob_end_clean();

			if (!class_exists(\AConfig::class))
			{
				$registry = null;

				return $registry;
			}

			$registry->loadObject(new AConfig());
		}

		return $registry;
	}
}