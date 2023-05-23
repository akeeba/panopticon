<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\PhpVersion;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Date\Date;
use DateInterval;
use DateTime;
use DateTimeZone;
use GuzzleHttp\ClientInterface;

class PhpVersion
{
	private const API_ENDPOINT = 'https://endoflife.date/api/php.json';

	private const CACHE_KEY = 'php_versions';

	private DateTime $expiration;

	public function __construct(private ?Container $container = null, private ?ClientInterface $httpClient = null)
	{
		$this->container ??= Factory::getContainer();

		if (empty($this->httpClient))
		{
			$today            = new Date();
			$this->expiration = (clone $today)->add(new DateInterval('P1W'));
			$interval         = $today->diff($this->expiration);

			$this->httpClient = $this->container->httpFactory->makeClient(
				cacheTTL: $interval->days * 86400
			);
		}
	}

	public function getVersionInformation(string $version): object
	{
		$version = Version::create($version)->versionFamily();
		$phpInfo = $this->getPhpEolInformation();

		$ret = (object) [
			'unknown'   => true,
			'supported' => false,
			'eol'       => false,
			'latest'    => null,
			'dates'     => (object) [
				'activeSupport' => null,
				'eol'           => null,
			],
		];

		if (!array_key_exists($version, $phpInfo))
		{
			return $ret;
		}

		$ret->unknown = false;
		$ret->latest  = $phpInfo[$version]->latestVersion;
		$ret->dates   = (object) [
			'activeSupport' => $phpInfo[$version]->activeSupport,
			'eol'           => $phpInfo[$version]->eol,
		];

		$today          = new Date();
		$ret->eol       = $ret->dates->eol->diff($today)->invert === 0;
		$ret->supported = !$ret->eol && !empty($ret->dates->activeSupport) && $ret->dates->activeSupport->diff($today)->invert === 1;

		return $ret;
	}

	public function getMinimumSupportedBranch(): string
	{
		$phpInfo = $this->getPhpEolInformation();

		return array_keys($phpInfo)[2];
	}

	public function getRecommendedSupportedBranch(): string
	{
		$phpInfo = $this->getPhpEolInformation();

		return array_keys($phpInfo)[1];
	}

    public function getLatestBranch(): string
    {
        $phpInfo = $this->getPhpEolInformation();

        return array_keys($phpInfo)[0];
    }

	public function isEOL(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return $versionInformation->unknown || $versionInformation->eol;
	}

	public function isSecurity(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return !$versionInformation->unknown && !$versionInformation->eol && !$versionInformation->supported;
	}

	public function getPhpEolInformation(): array
	{
		$cacheController = new CallbackController(
			container: $this->container,
			pool: $this->container->cacheFactory->pool(self::CACHE_KEY),
		);

		return $cacheController->get(
			function () {
				$json = $this->httpClient->get(self::API_ENDPOINT)?->getBody()?->getContents() ?: [];

				try
				{
					$rawData = @json_decode($json);
				}
				catch (\Throwable $e)
				{
					$rawData = [];
				}

				$ret      = [];

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
						'activeSupport' => new DateTime($rawItem->support, $timezone),
						'eol'           => new DateTime($rawItem->eol, $timezone),
						'releaseDate'   => new DateTime($rawItem->releaseDate, $timezone),
						'latestVersion' => $rawItem->latest,
					];
				}

				return $ret;
			},
			expiration: $this->expiration,
		);
	}
}