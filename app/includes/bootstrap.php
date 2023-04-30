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
	if (defined('AKEEBA_PANOPTICON_CLI') || @is_file(APATH_THEMES . '/system/incompatible.html'))
	{
		echo sprintf(
			"Akeeba Panopticon requires PHP %s or later. Your server is using PHP %s.". PHP_EOL,
			AKEEBA_PANOPTICON_MINPHP, PHP_VERSION
		);

		exit(254);
	}

	die(
	str_replace(
		['{{minphpversion}}', '{{phpversion}}'],
		[AKEEBA_PANOPTICON_MINPHP, PHP_VERSION],
		file_get_contents(APATH_THEMES . '/system/incompatible.html')
	)
	);
}

// We cannot possibly be running under HHVM?!!
if (defined('HHVM_VERSION'))
{
	if (defined('AKEEBA_PANOPTICON_CLI') || !@is_file(APATH_THEMES . '/system/hhvm.html'))
	{
		echo "Akeeba Panopticon is not compatible with HHVM. Please use PHP proper.". PHP_EOL;

		exit(254);
	}

	die(
		file_get_contents(APATH_THEMES . '/system/hhvm.html')
	);
}


// Check if the build process has run
if (!file_exists(APATH_ROOT . '/vendor/autoload.php') || !@is_file(APATH_THEMES . '/system/incomplete.html'))
{
	if (defined('AKEEBA_PANOPTICON_CLI'))
	{
		echo "Akeeba Panopticon has not been installed correctly." . PHP_EOL;

		exit(254);
	}

	die(
	file_get_contents(APATH_THEMES . '/system/incomplete.html')
	);
}

// Load the Composer autoloader
require_once APATH_ROOT . '/vendor/autoload.php';

/**
 * Overrides the Symfony HtmlErrorRenderer:
 * - Always give detailed information, even to the "simple" error page
 * - Allow overriding the debug template
 *
 * Why this in-memory patching trickery instead of overriding the class, you ask? There's a good reason!
 *
 * We need to override two private methods. Overriding a private method in a descendant class doesn't work (the parent
 * class' code will still use the parent class' private member instead of the one we defined in the descendant). This
 * means that we'd have to copy the entire class instead of extending from it. While possible, it makes it far harder
 * to update the code several months or years later when the overridden class breaks. Using in-memory patching we can
 * readily see the handful of lines we changed, making it easy to update.
 *
 * Kids, don't try this at home. We are trained professionals with over two decades of experience doing weird things in
 * PHP code.
 */
call_user_func(function() {
	// Loads the buffer class and registers the `awf://` stream handler.
	class_exists(\Awf\Utils\Buffer::class);

	// Override FlattenException
	$sourceCode = @file_get_contents(APATH_BASE . '/vendor/symfony/error-handler/Exception/FlattenException.php');

	$sourceCode = str_replace('$statusCode = 500;', <<< PHP
\$statusCode = in_array(\$exception->getCode(), [400, 401, 403, 404, 406, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 425, 428, 429, 431, 451, 500, 501, 502, 503, 504, 505, 506, 510, 511]) ? \$exception->getCode() : 500;
PHP
, $sourceCode);
	$sourceCode = str_replace('$statusText = \'Whoops, looks like something went wrong.\';', '$statusText = $exception->getMessage();', $sourceCode);


	$tempFile = 'awf://tmp/FlattenException.php';
	file_put_contents($tempFile, $sourceCode);
	require_once  $tempFile;
	@unlink($tempFile);

	// Override HtmlErrorRenderer
	$sourceCode = @file_get_contents(APATH_BASE . '/vendor/symfony/error-handler/ErrorRenderer/HtmlErrorRenderer.php');

	//$sourceCode = str_replace('if (!$debug) {', 'if (true) {', $sourceCode);
	$sourceCode = str_replace('return $this->include(self::$template, [', <<<PHP
return \$this->include(self::\$template, [
            'exception' => \$exception,
            'exceptionMessage' => \$this->escape(\$exception->getMessage()),
            'logger' => \$this->logger instanceof DebugLoggerInterface ? \$this->logger : null,
            'currentContent' => \is_string(\$this->outputBuffer) ? \$this->outputBuffer : (\$this->outputBuffer)(),

PHP
, $sourceCode);
	$sourceCode = str_replace(
		'include is_file(\dirname(__DIR__).\'/Resources/\'.$name) ? \dirname(__DIR__).\'/Resources/\'.$name : $name;',
		<<<PHP
	include array_reduce([
		APATH_THEMES . '/system/' . str_replace('views/', 'error/', \$name),
		APATH_BASE . '/vendor/symfony/error-handler/Resources/' . \$name,
		\$name
	], fn(\$carry, \$path) => \$carry ?? (file_exists(\$path) ? \$path : null), null);

PHP,
		$sourceCode
	);

	$tempFile = 'awf://tmp/HtmlErrorRenderer.php';
	file_put_contents($tempFile, $sourceCode);
	require_once  $tempFile;
	@unlink($tempFile);
});

// Set up the basic error handler for fatal errors
$errorHandler = \Symfony\Component\ErrorHandler\ErrorHandler::register();
\Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer::setTemplate(APATH_THEMES . '/system/fatal.php');

/**
 * Forcibly enable detailed exception reporting during the initial setup.
 */
if (!file_exists(APATH_CONFIGURATION . '/config.php'))
{
	//define('AKEEBADEBUG', 1);

	$errorHandler->setExceptionHandler(
		[
			new \Symfony\Component\ErrorHandler\ErrorHandler(null, true),
			'renderException'
		]
	);

	return;
}

// Load the configuration; we'll need it to set up error reporting and handling
ob_start();
require_once APATH_CONFIGURATION . '/config.php';
ob_end_clean();

$config = new AConfig();

// Set the PHP error reporting
switch ($config->error_reporting ?? 'default') {
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

// Do we need to set up a more detailed error handler?
if (!defined('AKEEBADEBUG')) {
	define('AKEEBADEBUG', $config->debug ?? false);
}

if (AKEEBADEBUG || ($config->error_reporting ?? 'default') === 'maximum') {
	// Set new Exception handler with debug enabled
	$errorHandler->setExceptionHandler(
		[
			new \Symfony\Component\ErrorHandler\ErrorHandler(null, true),
			'renderException'
		]
	);
}

// Tell AWF whether we are behind a proxy or load balancer
\Awf\Utils\Ip::setAllowIpOverrides($config->behind_load_balancer ?? false);

if (file_exists(APATH_USER_CODE . '/bootstrap.php'))
{
	require_once APATH_USER_CODE . '/bootstrap.php';
}

unset($config);