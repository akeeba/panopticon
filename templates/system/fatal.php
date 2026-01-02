<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
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

$replacements = [
	'{{statusCode}}'            => $statusCode,
	'{{statusText}}'            => $statusText,
	'{{statusCode_statusText}}' => $statusCode . ' - ' . $statusText,
	'{{message}}'               => call_user_func(
		function (string $message): string {
			$parts = array_map(
				'htmlentities',
				explode(PHP_EOL, $message)
			);

			$parts = array_map(
				function ($i, $string) {
					if ($i === 0) return $string;

					return sprintf('<span style="color: gray; font-size: small; display: inline-block; margin: 1em 0 0">%s</span>', $string);
				},
				array_keys($parts), array_values($parts)
			);

			return implode('<br>', $parts);
		},
		$exception->getMessage()
	),
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
