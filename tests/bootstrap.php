<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 *
 * PHPUnit bootstrap.
 *
 * This bootstrap deliberately does NOT instantiate the Panopticon Application: template loading,
 * MFA prompts, and the setup-redirect logic must not fire for tests. We construct ONLY the
 * Container so individual tests can resolve services (db, userManager, appConfig, etc.) without
 * starting an HTTP request lifecycle.
 *
 * Test isolation: integration tests should wrap each test in BEGIN/ROLLBACK via
 * Akeeba\Panopticon\Tests\AbstractIntegrationTestCase. DDL is not rolled back; schema is applied
 * once at bootstrap.
 */

declare(strict_types=1);

// Required by every Panopticon entry point
define('AKEEBA', 1);
define('AKEEBA_PANOPTICON_TEST', 1);

// AWF still calls mysqli_ping() which is E_DEPRECATED on PHP 8.4+. Suppressing here keeps
// stray output out of stdout so http_response_code() works inside the integration tests.
// Errors and warnings (E_WARNING / E_ERROR / etc.) are still reported — we silence only
// deprecations from third-party libraries.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Path constants
require __DIR__ . '/../defines.php';

// Composer autoloader (includes the Akeeba\Panopticon\Tests\ namespace via autoload-dev)
require APATH_ROOT . '/vendor/autoload.php';

// Version constants used by Container::__construct() for the session segment name
if (file_exists(APATH_ROOT . '/version.php'))
{
	require APATH_ROOT . '/version.php';
}

if (!defined('AKEEBA_PANOPTICON_VERSION'))
{
	define('AKEEBA_PANOPTICON_VERSION', 'test');
}

if (!defined('AKEEBA_PANOPTICON_DATE'))
{
	define('AKEEBA_PANOPTICON_DATE', gmdate('Y-m-d'));
}

// PANOPTICON_ENVIRONMENT must be 'test' so the live Dotenv loader picks up .env.test instead of
// .env.production. phpunit.xml sets this via <env>, but be defensive in case the bootstrap is
// invoked directly.
if (!isset($_SERVER['PANOPTICON_ENVIRONMENT']) && !isset($_ENV['PANOPTICON_ENVIRONMENT']))
{
	$_SERVER['PANOPTICON_ENVIRONMENT'] = 'test';
	$_ENV['PANOPTICON_ENVIRONMENT']    = 'test';
	putenv('PANOPTICON_ENVIRONMENT=test');
}

// -- Production-DB sanity guard --------------------------------------------------------------
// We refuse to run if the test DB name matches the production DB name. Production may be
// configured either via .env (preferred) or via config.php (legacy). Compare both.

/**
 * Parse a Panopticon .env file and return its variables as an associative array.
 *
 * @param   string  $path
 *
 * @return  array<string, string>
 */
$parseEnv = static function (string $path): array
{
	if (!is_file($path) || !is_readable($path))
	{
		return [];
	}

	try
	{
		return \Dotenv\Dotenv::parse(file_get_contents($path) ?: '');
	}
	catch (\Throwable)
	{
		return [];
	}
};

$testEnv = $parseEnv(APATH_ROOT . '/.env.test');

if (empty($testEnv['PANOPTICON_DBNAME']))
{
	fwrite(
		STDERR,
		"REFUSING to run tests: .env.test is missing or PANOPTICON_DBNAME is not set.\n"
		. "Copy .env.test.example to .env.test and configure a DEDICATED test database.\n"
	);
	exit(2);
}

$testDbName = (string) $testEnv['PANOPTICON_DBNAME'];

$productionDbNames = [];

// .env (production)
$prodEnv = $parseEnv(APATH_ROOT . '/.env');

if (!empty($prodEnv['PANOPTICON_DBNAME']))
{
	$productionDbNames[] = (string) $prodEnv['PANOPTICON_DBNAME'];
}

// .env.production
$prodEnv2 = $parseEnv(APATH_ROOT . '/.env.production');

if (!empty($prodEnv2['PANOPTICON_DBNAME']))
{
	$productionDbNames[] = (string) $prodEnv2['PANOPTICON_DBNAME'];
}

// Legacy config.php $dbname
$configPath = APATH_ROOT . '/config.php';

if (is_file($configPath) && is_readable($configPath))
{
	try
	{
		// AConfig is the class name used by Awf\Mvc\Configuration::saveConfiguration()
		if (!class_exists('AConfig', false))
		{
			$configSource = file_get_contents($configPath) ?: '';

			// Strip the leading PHP open tag + AKEEBA guard, then eval the assignment safely.
			// We tolerate the guard line by simply requiring the file in an isolated function.
			$loadConfig = static function () use ($configPath)
			{
				/** @noinspection PhpIncludeInspection */
				require $configPath;

				return class_exists('AConfig', false) ? new \AConfig() : null;
			};

			$config = $loadConfig();

			if ($config !== null && isset($config->dbname))
			{
				$productionDbNames[] = (string) $config->dbname;
			}
		}
	}
	catch (\Throwable)
	{
		// Ignore — config.php may be inaccessible or malformed; nothing more we can do.
	}
}

foreach (array_unique(array_filter($productionDbNames)) as $prodName)
{
	if ($prodName === $testDbName)
	{
		fwrite(
			STDERR,
			sprintf(
				"REFUSING to run tests: .env.test PANOPTICON_DBNAME (%s) matches the production database name.\n"
				. "Configure a DEDICATED test database and re-run.\n",
				$testDbName
			)
		);
		exit(2);
	}
}

// -- Build the Container ---------------------------------------------------------------------
// We deliberately do NOT instantiate Application — only the Container. The live Configuration
// service loads .env / .env.test based on PANOPTICON_ENVIRONMENT and exposes the values via
// $container->appConfig.

$container = \Akeeba\Panopticon\Factory::getContainer();

// Force the configuration to load now so we surface any .env.test issues at bootstrap time.
$container->appConfig->loadConfiguration();

// Wire up the Panopticon User subclass + privilege plugin. The live Application does this in
// its bootstrap path; tests must do it explicitly so $user->authorise(...) works.
\Akeeba\Panopticon\Application\BootstrapUtilities::setUpUserManager();

// Sanity: the loaded dbname must equal the .env.test value (defence against config.php winning
// over .env.test due to some path issue).
$loadedDbName = (string) $container->appConfig->get('dbname');

if ($loadedDbName !== '' && $loadedDbName !== $testDbName)
{
	fwrite(
		STDERR,
		sprintf(
			"REFUSING to run tests: loaded dbname (%s) does not match .env.test PANOPTICON_DBNAME (%s).\n"
			. "Something is loading a different configuration than .env.test. Aborting.\n",
			$loadedDbName,
			$testDbName
		)
	);
	exit(2);
}

// -- Apply schema once per suite -------------------------------------------------------------
// We use the same Awf\Database\Installer path the database:update CLI command uses.

try
{
	$container->db->connect();

	if (!$container->db->connected())
	{
		fwrite(STDERR, "REFUSING to run tests: cannot connect to the test database.\n");
		exit(2);
	}

	$installer = new \Awf\Database\Installer($container);
	$installer->setXmlDirectory(APATH_ROOT . '/src/schema');
	$installer->updateSchema();
}
catch (\Throwable $e)
{
	fwrite(
		STDERR,
		sprintf(
			"Failed to apply schema to the test database: %s\n%s\n",
			$e->getMessage(),
			$e->getTraceAsString()
		)
	);
	exit(2);
}

unset($parseEnv, $testEnv, $prodEnv, $prodEnv2, $productionDbNames, $configPath);
