<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
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
	use JsonSanitizerTrait;

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
			$users = @json_decode($this->sanitizeJson($response->getBody()->getContents()), flags: JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			return null;
		}

		if (empty($users) || !is_array($users))
		{
			return null;
		}

		// Filter out bots and other non-human users
		$users = array_filter($users, fn($x) => !is_object($x) || $x?->type === 'User');
		$users = array_filter($users, fn($x) => !in_array($x?->login, ['weblate']));

		if (empty($users))
		{
			return null;
		}

		return $users;
	}

	/**
	 * Get the information from the npm package-lock.json file.
	 *
	 * Reads the contents of the package-lock.json file and returns it as an associative array.
	 *
	 * @return  array  The contents of the package-lock.json file as an associative array.
	 * @since   1.0.6
	 */
	public function getNPMInformation(): array
	{
		$filePath = $this->container->basePath . '/vendor/composer/package-lock.json';

		if (!is_readable($filePath) || !is_file($filePath))
		{
			return [];
		}

		$contents = @file_get_contents($filePath);

		if (!$contents)
		{
			return [];
		}

		$return = json_decode($contents, true);

		if (!is_array($return))
		{
			return [];
		}

		return $return;
	}

	/**
	 * Get the list of Composer dependencies.
	 *
	 * Retrieves all the installed dependencies using Composer's InstalledVersions class and returns an array of version numbers.
	 *
	 * @return  array  An array of dependency version numbers.
	 * @since   1.0.6
	 */
	public function getDependencies(): array
	{
		$dependencies = [];

		foreach (\Composer\InstalledVersions::getAllRawData() as $item)
		{
			$dependencies = array_merge($dependencies, $item['versions']);
		}

		ksort($dependencies);

		return $dependencies;
	}
}