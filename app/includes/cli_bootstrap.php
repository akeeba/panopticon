<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Check the minimum PHP version
if (version_compare(
	PHP_VERSION,
	defined('AKEEBA_PANOPTICON_MINPHP') ? AKEEBA_PANOPTICON_MINPHP : '8.1.0',
	'lt'
))
{
	echo sprintf(
		"Akeeba Panopticon requires PHP %s or later. Your server is using PHP %s.",
		AKEEBA_PANOPTICON_MINPHP, PHP_VERSION
	);

	exit(254);
}

// We cannot possibly be running under HHVM?!!
if (defined('HHVM_VERSION'))
{
	echo "Akeeba Panopticon is not compatible with HHVM. Please use PHP proper.";

	exit(254);
}


// Check if the build process has run
if (!file_exists(APATH_ROOT . '/vendor/autoload.php'))
{
	echo "Akeeba Panopticon has not been installed correctly.";

	exit(254);
}

// Load the Composer autoloader
require_once APATH_ROOT . '/vendor/autoload.php';

// Set up the basic error handler for fatal errors
$errorHandler = \Symfony\Component\ErrorHandler\ErrorHandler::register();

if (!file_exists(APATH_CONFIGURATION . '/config.php'))
{
	echo "Please finish configuring Akeeba Panopticon before running this CLI scipt.";

	exit(254);
}

// Load the configuration; we'll need it to set up error reporting and handling
ob_start();
require_once APATH_CONFIGURATION . '/config.php';
ob_end_clean();

$config = new AConfig();

// Set the PHP error reporting
switch ($config->error_reporting) {
	default:
		break;

	case 'none':
		error_reporting(0);

		break;

	case 'simple':
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		ini_set('display_errors', 1);

		break;

	case 'maximum':
		error_reporting(E_ALL);
		ini_set('display_errors', 1);

		break;
}

// Do we need to set up a more detailed error handler?
if (!defined('AKEEBADEBUG')) {
	define('AKEEBADEBUG', $config->debug);
}

if (AKEEBADEBUG || $config->error_reporting === 'maximum') {
	// Set new Exception handler with debug enabled
	$errorHandler->setExceptionHandler(
		[
			new \Symfony\Component\ErrorHandler\ErrorHandler(null, true),
			'renderException'
		]
	);
}

// Tell AWF whether we are behind a proxy or load balancer
\Awf\Utils\Ip::setAllowIpOverrides($config->behind_load_balancer);

unset($config);