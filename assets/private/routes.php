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
 * @since  1.4.0
 */

use Awf\Router\Rule;

$apiParseCallable = function (string $path): ?array
{
	$path = trim($path, '/');

	// Only handle paths starting with "api/"
	if (!str_starts_with($path, 'api/'))
	{
		return null;
	}

	// Strip "api/" prefix
	$apiPath = substr($path, 4);
	$apiPath = trim($apiPath, '/');

	if (empty($apiPath))
	{
		return null;
	}

	$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
	$segments = explode('/', $apiPath);
	$vars     = ['view' => 'api'];

	// Route map: pattern => [method => handler class]
	// We match against the segments array
	$handler = null;

	// v1/sites — GET → V1\Site\GetList
	if ($segments === ['v1', 'sites'] && $method === 'GET')
	{
		$handler = 'V1\\Site\\GetList';
	}
	// v1/site — PUT → V1\Site\Add
	elseif ($segments === ['v1', 'site'] && $method === 'PUT')
	{
		$handler = 'V1\\Site\\Add';
	}
	// v1/site/:id/...
	elseif (count($segments) >= 3 && $segments[0] === 'v1' && $segments[1] === 'site' && ctype_digit($segments[2]))
	{
		$vars['id'] = (int) $segments[2];
		$rest       = array_slice($segments, 3);

		if ($rest === [] && $method === 'GET')
		{
			$handler = 'V1\\Site\\Get';
		}
		elseif ($rest === [] && $method === 'POST')
		{
			$handler = 'V1\\Site\\Modify';
		}
		elseif ($rest === ['refresh'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\Refresh';
		}
		elseif ($rest === ['fixjoomlacoreupdate'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\FixJoomlaCoreUpdate';
		}
		elseif ($rest === ['extensions'] && $method === 'GET')
		{
			$handler = 'V1\\Site\\ExtensionsList';
		}
		elseif ($rest === ['extensions'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\ExtensionsRefresh';
		}
		elseif ($rest === ['cmsupdate'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\CmsUpdate';
		}
		elseif ($rest === ['cmsupdate', 'cancel'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\CmsUpdateCancel';
		}
		elseif ($rest === ['cmsupdate', 'clear'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\CmsUpdateClear';
		}
		elseif ($rest === ['extensions', 'clear'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\ExtensionsClear';
		}
		elseif ($rest === ['extensions', 'reset'] && $method === 'POST')
		{
			$handler = 'V1\\Site\\ExtensionsReset';
		}
		elseif (
			count($rest) === 3 && $rest[0] === 'extensions' && $rest[1] === 'scheduleupdate'
			&& ctype_digit($rest[2]) && $method === 'POST'
		)
		{
			$vars['extId'] = (int) $rest[2];
			$handler       = 'V1\\Site\\ExtensionScheduleUpdate';
		}
		elseif (
			count($rest) === 3 && $rest[0] === 'extensions' && $rest[1] === 'cancel'
			&& ctype_digit($rest[2]) && $method === 'POST'
		)
		{
			$vars['extId'] = (int) $rest[2];
			$handler       = 'V1\\Site\\ExtensionCancelUpdate';
		}
		elseif (
			count($rest) === 3 && $rest[0] === 'extension' && ctype_digit($rest[1])
			&& $rest[2] === 'downloadkey' && $method === 'GET'
		)
		{
			$vars['extId'] = (int) $rest[1];
			$handler       = 'V1\\Site\\ExtensionDownloadKeyGet';
		}
		elseif (
			count($rest) === 3 && $rest[0] === 'extension' && ctype_digit($rest[1])
			&& $rest[2] === 'downloadkey' && $method === 'POST'
		)
		{
			$vars['extId'] = (int) $rest[1];
			$handler       = 'V1\\Site\\ExtensionDownloadKeySet';
		}
	}
	// v1/sysconfig — GET → V1\Sysconfig\GetList
	elseif ($segments === ['v1', 'sysconfig'] && $method === 'GET')
	{
		$handler = 'V1\\Sysconfig\\GetList';
	}
	// v1/sysconfig/:paramName
	elseif (count($segments) === 3 && $segments[0] === 'v1' && $segments[1] === 'sysconfig')
	{
		$vars['paramName'] = $segments[2];

		if ($method === 'GET')
		{
			$handler = 'V1\\Sysconfig\\Get';
		}
		elseif ($method === 'POST')
		{
			$handler = 'V1\\Sysconfig\\Set';
		}
	}
	// v1/tasks — GET → V1\Task\GetList
	elseif ($segments === ['v1', 'tasks'] && $method === 'GET')
	{
		$handler = 'V1\\Task\\GetList';
	}
	// v1/task — PUT → V1\Task\Add
	elseif ($segments === ['v1', 'task'] && $method === 'PUT')
	{
		$handler = 'V1\\Task\\Add';
	}
	// v1/task/:id
	elseif (count($segments) === 3 && $segments[0] === 'v1' && $segments[1] === 'task' && ctype_digit($segments[2]))
	{
		$vars['id'] = (int) $segments[2];

		if ($method === 'GET')
		{
			$handler = 'V1\\Task\\Get';
		}
		elseif ($method === 'POST')
		{
			$handler = 'V1\\Task\\Modify';
		}
	}
	// v1/selfupdate — GET → V1\Selfupdate\Info
	elseif ($segments === ['v1', 'selfupdate'] && $method === 'GET')
	{
		$handler = 'V1\\Selfupdate\\Info';
	}
	// v1/selfupdate/download — GET → V1\Selfupdate\Download
	elseif ($segments === ['v1', 'selfupdate', 'download'] && $method === 'GET')
	{
		$handler = 'V1\\Selfupdate\\Download';
	}
	// v1/selfupdate/install — GET → V1\Selfupdate\Install
	elseif ($segments === ['v1', 'selfupdate', 'install'] && $method === 'GET')
	{
		$handler = 'V1\\Selfupdate\\Install';
	}
	// v1/selfupdate/postinstall — GET → V1\Selfupdate\Postinstall
	elseif ($segments === ['v1', 'selfupdate', 'postinstall'] && $method === 'GET')
	{
		$handler = 'V1\\Selfupdate\\Postinstall';
	}

	if ($handler === null)
	{
		return null;
	}

	$vars['handler'] = $handler;

	return $vars;
};

$router->addRule(new Rule([
	'parseCallable' => $apiParseCallable,
]));
