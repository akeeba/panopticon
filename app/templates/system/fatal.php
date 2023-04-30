<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBA') || die;

use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

/**
 * Used when a fatal error occurs. These may happen before the application is fully initialised. The existence of an
 * application container is not guaranteed.
 *
 * For regular exceptions handling we use the error.php file, not this one.
 *
 * @var  HtmlErrorRenderer $this       object containing charset
 * @var  string            $statusText exception error message
 * @var  string            $statusCode exception error code
 */

//$exception = debug_backtrace()[2]['args'][0] ?? new RuntimeException($statusText, $statusCode);

$replacements = [
	'{{statusCode}}'            => $statusCode,
	'{{statusText}}'            => $statusText,
	'{{statusCode_statusText}}' => $statusCode . ' - ' . $statusText,
	'{{message}}'               => $exception->getMessage(),
	'{{code}}'                  => $exception->getCode(),
	'{{file}}'                  => $exception->getFile(),
	'{{line}}'                  => $exception->getLine(),
	'{{backtrace}}'             => $exception->getTraceAsString(),
];

// Fallback template
$template = @file_exists(__DIR__ . '/fatal.html')
	? @file_get_contents(__DIR__ . '/fatal.html')
	: '';

$template = $template ?: '{{statusCode_statusText}}';

echo str_replace(
	array_keys($replacements),
	array_values($replacements),
	$template
);
