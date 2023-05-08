<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Task;


use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;

defined('AKEEBA') || die;

trait ApiRequestTrait
{
	protected function getRequestOptions(Site $site, string $path)
	{
		$url            = $site->url . $path;
		$totalTimeout   = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
		$connectTimeout = max(5, $totalTimeout / 5);

		$authHeaders = $this->getAuthenticationHeaders($site);
		$options     = [
			'headers'         => array_merge($authHeaders, [
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			]),
			'connect_timeout' => $connectTimeout,
			'timeout'         => $totalTimeout,
		];

		if (defined('AKEEBA_CACERT_PEM'))
		{
			$options['verify'] = AKEEBA_CACERT_PEM;
		}

		return [$url, $options];
	}

	protected function getAuthenticationHeaders(Site $site): array
	{
		$config   = ($site->config instanceof Registry) ? $site->config : new Registry($site->config);
		$apiKey   = $config->get('config.apiKey', '');
		$username = $config->get('config.username', '');
		$password = $config->get('config.password', '');

		$headers = [];

		if (!empty($apiKey))
		{
			$authHeader                = 'Bearer ' . $apiKey;
			$headers['Authorization']  = $authHeader;
			$headers['X-Joomla-Token'] = $apiKey;
		}
		else
		{
			$headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
		}

		return $headers;
	}
}