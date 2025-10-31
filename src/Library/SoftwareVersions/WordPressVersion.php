<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SoftwareVersions;


use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Version\Version;
use DateInterval;
use DateTime;
use DateTimeZone;
use GuzzleHttp\ClientInterface;

defined('AKEEBA') || die;

class WordPressVersion
{
	private const API_ENDPOINT = 'https://endoflife.date/api/wordpress.json';

	private DateTime $expiration;

	public function __construct(private ?Container $container = null, private ?ClientInterface $httpClient = null)
	{
		$this->container ??= Factory::getContainer();

		if (empty($this->httpClient))
		{
			$today            = $this->container->dateFactory();
			$this->expiration = (clone $today)->add(new DateInterval('P1W'));
			$interval         = $today->diff($this->expiration);

			$this->httpClient = $this->container->httpFactory->makeClient(
				cacheTTL: $interval->days * 86400
			);
		}
	}

	/**
	 * Get the WordPress release dates and End-of-Life information per version family.
	 *
	 * @return  array
	 * @throws  \Psr\Cache\CacheException
	 * @throws  \Psr\Cache\InvalidArgumentException
	 * @since  1.0.1
	 * @see    https://endoflife.date/wordpress
	 */
	public function getWordPressEolInformation(): array
	{
		$cacheController = new CallbackController(
			container: $this->container,
			pool: $this->container->cacheFactory->pool('system'),
		);

		return $cacheController->get(
			function () {
				$options = $this->container->httpFactory->getDefaultRequestOptions();
				$json    = $this->httpClient->get(self::API_ENDPOINT, $options)?->getBody()?->getContents() ?: [];

				try
				{
					$rawData = @json_decode($json);
				}
				catch (\Throwable)
				{
					$rawData = [];
				}

				$ret = [];

				try
				{
					$timezone = new DateTimeZone('UTC');
				}
				catch (\Throwable)
				{
					$timezone = null;
				}

				foreach ($rawData as $rawItem)
				{
					$ret[$rawItem->cycle] = (object) [
						'cycle'        => $rawItem->cycle,
						'firstRelease' => $rawItem->releaseDate,
						'eol'          => $rawItem->eol ? new DateTime($rawItem->eol, $timezone) : null,
					];
				}

				return $ret;
			},
			id: 'wordpress_versions',
			expiration: $this->expiration
		);
	}

	/**
	 * Get support status information for a WordPress version
	 *
	 * @param   string         $version  The version to check, e.g. 6.4.2
	 * @param   DateTime|null  $today    The date against to check. NULL to use the current date and time
	 *
	 * @return  object
	 * @throws  \Psr\Cache\CacheException
	 * @throws  \Psr\Cache\InvalidArgumentException
	 */
	public function getVersionInformation(string $version, ?DateTime $today = null): object
	{
		// Initialisation
		$wordPressInfo = $this->getWordPressEolInformation();
		$versionObject = Version::create($version);

		$ret = (object) [
			'series'    => null,
			'unknown'   => true,
			'supported' => false,
			'eol'       => false,
			'dates'     => (object) [
				'firstRelease' => null,
				'eol'          => null,
			],
		];

		$series     = $versionObject->versionFamily();
		$familyInfo = $wordPressInfo[$series] ?? null;

		if (empty($familyInfo))
		{
			return $ret;
		}

		$ret->series              = $familyInfo->cycle;
		$ret->unknown             = false;
		$ret->dates->firstRelease = $familyInfo->firstRelease;
		$ret->dates->eol          = $familyInfo->eol;

		$today          ??= new DateTime();
		$ret->eol       = $ret->dates->eol !== null && $ret->dates->eol->diff($today)->invert === 0;
		$ret->supported = !$ret->eol;

		return $ret;
	}

	public function isEOL(?string $version): bool
	{
		if (empty($version))
		{
			return false;
		}

		$versionInformation = $this->getVersionInformation($version);

		return $versionInformation->eol;
	}
}