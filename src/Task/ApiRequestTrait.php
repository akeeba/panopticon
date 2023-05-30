<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;


use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;
use GuzzleHttp\RequestOptions;

defined('AKEEBA') || die;

trait ApiRequestTrait
{
	protected function getRequestOptions(Site $site, string $path)
	{
		// Get the default options
		$options = $this->container->httpFactory->getDefaultRequestOptions();

		// Add the authentication options
		$authHeaders                      = $this->getAuthenticationHeaders($site);
		$options[RequestOptions::HEADERS] = array_merge(
			$authHeaders,
			[
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			]
		);

		// Add timeout setting
		$totalTimeout                     = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
		$options[RequestOptions::TIMEOUT] = $totalTimeout;

		// Add connection timeout setting
		$connectTimeout                           = max(5, $totalTimeout / 5);
		$options[RequestOptions::CONNECT_TIMEOUT] = $connectTimeout;

		// Construct the API URL
		$url = $site->url . $path;

		// Return the results
		return [$url, $options];
	}

	protected function getAuthenticationHeaders(Site $site): array
	{
		$config   = $site->getFieldValue('config') ?? '{}';
		$config   = ($config instanceof Registry) ? $config : new Registry($config);
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