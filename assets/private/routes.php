<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * API Route Parser
 *
 * Maps API URL paths + HTTP methods to handler class names under src/Controller/Api/.
 *
 * The route table below is consumed by a single generic matcher. Each entry is a tuple of
 * `[METHOD, [segment, segment, ...], handlerClassSuffix]`. Segments that start with a colon
 * are placeholders captured into `$vars` under the placeholder name (sans colon). The
 * placeholder names `id` and `extId` additionally require the segment to be a non-negative
 * integer (i.e. `ctype_digit()` true); all other placeholders accept any non-empty segment.
 * First match wins; an unmatched request returns null so the dispatcher emits a 404.
 *
 * @since  1.4.0
 */

use Awf\Router\Rule;

$apiParseCallable = function (string $path): ?array
{
	$path = trim($path, '/');

	// AWF strips most prefixes but some SAPI/rewrite combos leave "index.php/" in front;
	// strip it ourselves so the API route always matches regardless of REQUEST_URI shape.
	if (str_starts_with($path, 'index.php/'))
	{
		$path = substr($path, 10);
	}

	// Only handle paths whose first segment is "api"
	if ($path !== 'api' && !str_starts_with($path, 'api/'))
	{
		return null;
	}

	// Strip the leading "api" segment
	$apiPath = ($path === 'api') ? '' : substr($path, 4);
	$apiPath = trim($apiPath, '/');

	if (empty($apiPath))
	{
		return null;
	}

	$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
	$segments = explode('/', $apiPath);

	// Route table: [METHOD, [path segments…], handler-class-suffix].
	// Placeholders are segments that start with ':'. The names `id` / `extId` require ctype_digit().
	$routes = [
		['GET',  ['v1', 'sites'],                                                'V1\\Site\\GetList'],
		['PUT',  ['v1', 'site'],                                                 'V1\\Site\\Add'],
		['GET',  ['v1', 'site', ':id'],                                          'V1\\Site\\Get'],
		['POST', ['v1', 'site', ':id'],                                          'V1\\Site\\Modify'],
		['POST', ['v1', 'site', ':id', 'refresh'],                               'V1\\Site\\Refresh'],
		['POST', ['v1', 'site', ':id', 'fixjoomlacoreupdate'],                   'V1\\Site\\FixJoomlaCoreUpdate'],
		['GET',  ['v1', 'site', ':id', 'extensions'],                            'V1\\Site\\ExtensionsList'],
		['POST', ['v1', 'site', ':id', 'extensions'],                            'V1\\Site\\ExtensionsRefresh'],
		['POST', ['v1', 'site', ':id', 'extensions', 'clear'],                   'V1\\Site\\ExtensionsClear'],
		['POST', ['v1', 'site', ':id', 'extensions', 'reset'],                   'V1\\Site\\ExtensionsReset'],
		['POST', ['v1', 'site', ':id', 'extensions', 'scheduleupdate', ':extId'], 'V1\\Site\\ExtensionScheduleUpdate'],
		['POST', ['v1', 'site', ':id', 'extensions', 'cancel', ':extId'],        'V1\\Site\\ExtensionCancelUpdate'],
		['GET',  ['v1', 'site', ':id', 'extension', ':extId', 'downloadkey'],    'V1\\Site\\ExtensionDownloadKeyGet'],
		['POST', ['v1', 'site', ':id', 'extension', ':extId', 'downloadkey'],    'V1\\Site\\ExtensionDownloadKeySet'],
		['POST', ['v1', 'site', ':id', 'cmsupdate'],                             'V1\\Site\\CmsUpdate'],
		['POST', ['v1', 'site', ':id', 'cmsupdate', 'cancel'],                   'V1\\Site\\CmsUpdateCancel'],
		['POST', ['v1', 'site', ':id', 'cmsupdate', 'clear'],                    'V1\\Site\\CmsUpdateClear'],
		['GET',  ['v1', 'sysconfig'],                                            'V1\\Sysconfig\\GetList'],
		['GET',  ['v1', 'sysconfig', ':paramName'],                              'V1\\Sysconfig\\Get'],
		['POST', ['v1', 'sysconfig', ':paramName'],                              'V1\\Sysconfig\\Set'],
		['GET',  ['v1', 'tasks'],                                                'V1\\Task\\GetList'],
		['PUT',  ['v1', 'task'],                                                 'V1\\Task\\Add'],
		['GET',  ['v1', 'task', ':id'],                                          'V1\\Task\\Get'],
		['POST', ['v1', 'task', ':id'],                                          'V1\\Task\\Modify'],
		['GET',  ['v1', 'selfupdate'],                                           'V1\\Selfupdate\\Info'],
		['GET',  ['v1', 'selfupdate', 'download'],                               'V1\\Selfupdate\\Download'],
		['GET',  ['v1', 'selfupdate', 'install'],                                'V1\\Selfupdate\\Install'],
		['GET',  ['v1', 'selfupdate', 'postinstall'],                            'V1\\Selfupdate\\Postinstall'],
	];

	foreach ($routes as [$routeMethod, $pattern, $handler])
	{
		if ($routeMethod !== $method)
		{
			continue;
		}

		if (count($pattern) !== count($segments))
		{
			continue;
		}

		$vars      = ['view' => 'api'];
		$matchedOk = true;

		foreach ($pattern as $i => $patternSegment)
		{
			if (str_starts_with($patternSegment, ':'))
			{
				$paramName  = substr($patternSegment, 1);
				$incoming   = $segments[$i];

				if ($incoming === '')
				{
					$matchedOk = false;
					break;
				}

				if (($paramName === 'id' || $paramName === 'extId') && !ctype_digit($incoming))
				{
					$matchedOk = false;
					break;
				}

				$vars[$paramName] = ($paramName === 'id' || $paramName === 'extId')
					? (int) $incoming
					: $incoming;

				continue;
			}

			if ($patternSegment !== $segments[$i])
			{
				$matchedOk = false;
				break;
			}
		}

		if (!$matchedOk)
		{
			continue;
		}

		$vars['handler'] = $handler;

		return $vars;
	}

	return null;
};

$router->addRule(new Rule([
	'parseCallable' => $apiParseCallable,
]));
