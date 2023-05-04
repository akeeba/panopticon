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
use Gt\Dom\HTMLDocument;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;

class PhpVersion
{
	private DateTime $expiration;

	public function __construct(private ?Container $container = null, private ?ClientInterface $httpClient = null)
	{
		$this->container ??= Factory::getContainer();

		/**
		 * Get a suitable caching time for PHP version information.
		 *
		 * PHP only releases a new version every mid-November to mid-December. If the date is between November 15th and
		 * December 20th we cache the version information for three days. In any other case we cache it until the next
		 * November 15th.
		 */
		$today = new Date();

		if (
			($today->month == 11 && $today->day >= 15)
			|| ($today->month == 12 && $today->day <= 20)
		)
		{
			$this->expiration = (clone $today)->add(new DateInterval('P3D'));
		}
		elseif ($today->month == 12)
		{
			$this->expiration = (clone $today)->setDate((int) $today->year + 1, 11, 15);
		}
		else
		{
			$this->expiration = (clone $today)->setDate($today->year, 11, 15);
		}

		if (empty($this->httpClient))
		{
			$interval = $today->diff($this->expiration);

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
			'dates'     => (object) [
				'initialRelease' => null,
				'activeSupport'  => null,
				'eol'            => null,
			],
		];

		if (!array_key_exists($version, $phpInfo))
		{
			return $ret;
		}

		$ret->unknown = false;
		$ret->dates   = (object) $phpInfo[$version];

		$today = new Date();
		$ret->eol = $ret->dates->diff($today)->invert === 0;
		$ret->supported = !$ret->eol && !empty($ret->dates->activeSupport) && $ret->dates->activeSupport->diff($today)->invert === 1;

		return $ret;
	}

	public function isEOL(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return $versionInformation->unknown || $versionInformation->eol;
	}

	public function isSecurity(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return !$versionInformation->unknown && !$versionInformation->eol && !$versionInformation->activeSupport;
	}

	public function getPhpEolInformation(): array
	{
		$cacheController = new CallbackController($this->container);

		return $cacheController->get(
			fn() => $this->realGetPhpEolInformation(),
			expiration: $this->expiration,
			namespace: 'php_eol_information',
		);
	}

	private function realGetPhpEolInformation(): array
	{
		$contents = $this->getAllURLs();

		try
		{
			$ret = $this->scrapeSupportedVersions($contents['supported'] ?? null);
		}
		catch (\Throwable $e)
		{
			$ret = [];
		}

		try
		{
			$data = $this->scrapeEolVersions($contents['eol'] ?? null);
			$ret  = array_merge($ret, $data);
		}
		catch (\Throwable $e)
		{
		}

		foreach ($this->scrapeDownloadsPage($contents['downloads'] ?? null) as $version => $date)
		{
			$v       = Version::create($version);
			$version = $v->major() . '.' . $v->minor();

			$ret[$version]['initialRelease'] = $date;
		}

		return $ret;
	}

	private function getAllURLs(): array
	{
		$promises = [
			'downloads' => $this->httpClient->getAsync('https://www.php.net/releases/'),
			'supported' => $this->httpClient->getAsync('https://www.php.net/supported-versions.php'),
			'eol'       => $this->httpClient->getAsync('https://www.php.net/eol.php'),
		];

		$responses = Utils::settle($promises)->wait();
		$ret       = [];

		foreach ($responses as $key => $response)
		{
			if ($response['state'] === 'rejected')
			{
				$ret[$key] = null;

				continue;
			}

			$ret[$key] = $response['value']?->getBody()?->getContents();
		}

		return $ret;
	}

	private function getFromURL(string $url): ?string
	{
		return $this->httpClient->get($url)?->getBody()?->getContents();
	}

	private function scrapeDownloadsPage(?string $html): array
	{
		$html = $html ?? $this->getFromURL('https://www.php.net/releases/');

		$ret      = [];
		$document = new HTMLDocument($html);

		foreach ($document->querySelectorAll('h2') as $heading)
		{
			$version = trim($heading->textContent);
			$li      = $heading->nextElementSibling->children;
			[, $dateRaw] = explode(':', $li[0]->textContent);
			$ret[$version] = new Date($dateRaw);
		}

		return $ret;
	}

	private function scrapeSupportedVersions(?string $html): array
	{
		$html = $html ?? $this->getFromURL('https://www.php.net/supported-versions.php');

		$ret      = [];
		$document = new HTMLDocument($html);

		foreach ($document->querySelectorAll('table.standard tbody tr') as $row)
		{
			$cells          = $row->querySelectorAll('td');
			$version        = trim($cells[0]->textContent);
			$initialRelease = new Date($cells[1]->textContent);
			$activeSupport  = new Date($cells[3]->textContent);
			$eol            = new Date($cells[5]->textContent);

			$ret[$version] = [
				'initialRelease' => $initialRelease,
				'activeSupport'  => $activeSupport,
				'eol'            => $eol,
			];
		}

		return $ret;
	}

	private function scrapeEolVersions(?string $html): array
	{
		$html = $html ?? $this->getFromURL('https://www.php.net/eol.php');

		$ret      = [];
		$document = new HTMLDocument($html);

		foreach ($document->querySelectorAll('table.standard tbody tr') as $row)
		{
			$cells   = $row->querySelectorAll('td');
			$version = trim($cells[0]->textContent);
			[$eol,] = explode('<br', $cells[1]->innerHTML);

			$ret[$version] = [
				'initialRelease' => null,
				'activeSupport'  => null,
				'eol'            => new Date($eol),
			];
		}

		return $ret;
	}
}