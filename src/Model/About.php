<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;

/**
 * About Page Model
 *
 * @since  1.0.0
 */
class About extends Model
{
	private const GITHUB_REPO = 'akeeba/panopticon';

	/**
	 * Get the top contributors information from GitHub.
	 *
	 * The information is cached for 24 hours.
	 *
	 * @return  array|null  NULL when we cannot retrieve that information (GitHub may have applied a request limit).
	 * @throws  GuzzleException
	 * @since   1.0.0
	 */
	public function getContributors(): ?array
	{
		$url = sprintf(
			'https://api.github.com/repos/%s/contributors?q=contributions&order=desc', self::GITHUB_REPO
		);

		$options                                  = $this->container->httpFactory->getDefaultRequestOptions();
		$options[RequestOptions::HEADERS]         = [
			'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
		];
		$options[RequestOptions::TIMEOUT]         = 5.0;
		$options[RequestOptions::CONNECT_TIMEOUT] = 5.0;

		/** @var Client $client */
		$client = $this->container->httpFactory->makeClient(
			clientOptions: $options, cacheTTL: 86400, singleton: false
		);

		$response = $client->get($url);

		if ($response->getStatusCode() !== 200)
		{
			return null;
		}

		try
		{
			$users = @json_decode($response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			return null;
		}

		if (empty($users) || !is_array($users))
		{
			return null;
		}

		$users = array_filter($users, fn($x) => !is_object($x) || $x?->type === 'User');
		if (empty($users))
		{
			return null;
		}

		return $users;
	}
}