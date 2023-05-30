<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\SelfUpdate\UpdateInformation;
use Akeeba\Panopticon\Library\SelfUpdate\VersionInformation;
use Awf\Mvc\Model;
use DateInterval;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use RuntimeException;

class Selfupdate extends Model
{
	/**
	 * @var string The currently installed version
	 */
	private string $currentVersion;

	/**
	 * @var string The minimum stability supported
	 */
	private string $minStability;

	/**
	 * @var string The URL of the update stream for this software
	 */
	private string $updateStreamUrl;

	public function __construct(Container $container = null)
	{
		parent::__construct($container);

		$this->currentVersion  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';
		$this->minStability    = $this->container->appConfig->get('selfupdate_min_stability', 'stable');
		$this->updateStreamUrl = $this->container['updateStreamUrl'] ?? 'https://api.github.com/repos/akeeba/panopticon/releases';
	}

	/**
	 * Get the update information
	 *
	 * @param   bool  $force  Set to true to bypass the update cache.
	 *
	 * @return  UpdateInformation
	 * @throws  \Psr\Cache\CacheException
	 * @throws  \Psr\Cache\InvalidArgumentException
	 */
	public function getUpdateInformation(bool $force = false): UpdateInformation
	{
		$cacheController = new CallbackController(
			container: $this->container,
			pool: $this->container->cacheFactory->pool('self_update')
		);

		return $cacheController->get(function () use ($force): UpdateInformation {
			/** @var Client $httpClient */
			$httpClient = $this->container->httpFactory->makeClient(cache: !$force, cacheTTL: 21600);
			$options    = $this->container->httpFactory->getDefaultRequestOptions();

			$options[RequestOptions::TIMEOUT] = 5.0;

			if (str_contains($this->updateStreamUrl, 'api.github.com'))
			{
				$options[RequestOptions::HEADERS] = array_merge(
					$options[RequestOptions::HEADERS] ?? [],
					[
						'Accept'     => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28',
						'User-Agent' => 'panopticon/' . $this->currentVersion,
					]
				);
			}

			$updateInfo = new UpdateInformation();

			try
			{
				$response = $httpClient->get($this->updateStreamUrl, $options);
			}
			catch (GuzzleException $e)
			{
				$updateInfo->error            = $e->getMessage();
				$updateInfo->errorLocation    = $e->getFile() . ':' . $e->getLine();
				$updateInfo->errorTraceString = $e->getTraceAsString();

				return $updateInfo;
			}

			$updateInfo->stuck            = false;
			$updateInfo->error            = null;
			$updateInfo->errorLocation    = null;
			$updateInfo->errorTraceString = null;

			$json = $response->getBody()->getContents();

			try
			{
				$rawData = @json_decode($json);
			}
			catch (\Exception $e)
			{
				$rawData = null;
			}

			if (empty($rawData) || !is_array($rawData))
			{
				return $updateInfo;
			}

			$updateInfo->populateVersionsFromGitHubReleases($rawData);
			$updateInfo->loadedUpdate = !empty($updateInfo->versions);

			return $updateInfo;
		}, id: 'updateInformation', expiration: $force ? 0 : new DateInterval('PT6H'));
	}

	public function getLatestVersion(bool $force = false): ?VersionInformation
	{
		$updateInfo = $this->getUpdateInformation($force);

		if (empty($updateInfo->versions))
		{
			return null;
		}

		$versions    = array_keys($updateInfo->versions);
		$bestVersion = array_reduce(
			$versions,
			fn($carry, $someVersion) => empty($carry) ? $someVersion : (
			version_compare($carry, $someVersion, 'lt') ? $someVersion : $carry
			),
			null
		);

		if (empty($bestVersion))
		{
			return null;
		}

		return $updateInfo->versions[$bestVersion];
	}

	public function hasUpdate(bool $force = false): bool
	{
		$latest = $this->getLatestVersion($force);

		if ($latest === null)
		{
			return false;
		}

		if (version_compare($this->currentVersion, $latest->version, 'ge'))
		{
			return false;
		}

		if (version_compare(PHP_VERSION, $this->extractMinimumPHP($latest), 'lt'))
		{
			return false;
		}

		return true;
	}

	public function extractMinimumPHP(VersionInformation $versionInformation): string
	{
		$notes = str_replace("\r\n", "\n", $versionInformation->releaseNotes);
		$notes = str_replace("\r", "\n", $notes);

		foreach (explode("\n", $notes) as $line)
		{
			$line = ltrim($line, "\t\ *");

			if (!str_starts_with($line, 'PHP'))
			{
				continue;
			}

			$line = ltrim($line, "PH ");
			[$minVersion,] = explode(' ', $line);

			return $minVersion;
		}

		return defined('AKEEBA_PANOPTICON_MINPHP') ? AKEEBA_PANOPTICON_MINPHP : (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
	}

	public function download(): string
	{
		if (!$this->hasUpdate())
		{
			throw new RuntimeException('There is no available update');
		}

		$url = $this->getLatestVersion()->downloadUrl;

		if (empty($url))
		{
			throw new RuntimeException('The latest version does not have a valid download URL');
		}

		if (defined('APATH_TMP') && is_dir(APATH_TMP) && is_writable(APATH_TMP))
		{
			$targetLocation = APATH_TMP . '/update.zip';
		}
		else
		{
			$targetLocation = sys_get_temp_dir() . '/update.zip';
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		$options = $this->container->httpFactory->getDefaultRequestOptions();

		$options[RequestOptions::TIMEOUT] = 5.0;

		// If the file already exists do a HEAD to see if we have already downloaded it.
		try
		{
			if (is_file($targetLocation))
			{
				$oldLength = @filesize($targetLocation) ?: 0;
				$oldMD5    = md5_file($targetLocation) ?: '00000000000000000000000000000000';
				$response  = $httpClient->head($url, $options);
				$newLength = $response->getHeader('Content-Length');
				$newMD5    = $response->getHeader('Content-MD5');
				$newLength = is_array($newLength) ? array_pop($newLength) : $newLength;
				$newMD5    = is_array($newMD5) ? array_pop($newMD5) : $newMD5;
				$newMD5    = empty($newMD5) ? null : bin2hex(base64_decode($newMD5));

				if ($oldLength === $newLength && (empty($newMD5) || strtolower($newMD5) === strtolower($oldMD5)))
				{
					return $targetLocation;
				}

				@unlink($targetLocation);
			}
		}
		catch (GuzzleException $e)
		{
			// No worries if it failed.
			@unlink($targetLocation);
		}

		// Download the file
		$options['sink'] = $targetLocation;
		$response        = $httpClient->get($url, $options);

		return $targetLocation;
	}

	public function extract(?string $sourceFile = null, ?string $targetPath = null): bool
	{
		if (empty($sourceFile))
		{
			if (defined('APATH_TMP') && is_dir(APATH_TMP) && is_writable(APATH_TMP))
			{
				$sourceFile = APATH_TMP . '/update.zip';
			}
			else
			{
				$sourceFile = sys_get_temp_dir() . '/update.zip';
			}
		}

		$targetPath ??= APATH_ROOT;

		$zip = new \ZipArchive();
		switch ($zip->open($sourceFile))
		{
			case \ZipArchive::ER_INCONS:
				throw new RuntimeException(sprintf('Update file %s is inconsistent.', $targetPath));
				break;

			case \ZipArchive::ER_INVAL:
			case \ZipArchive::ER_MEMORY:
				throw new RuntimeException(sprintf('Cannot open update file %s: internal error in PHP', $targetPath));
				break;

			case \ZipArchive::ER_NOENT:
				throw new RuntimeException(sprintf('Update file %s does not exist.', $targetPath));
				break;

			case \ZipArchive::ER_NOZIP:
				throw new RuntimeException(sprintf('Update file %s is not a ZIP archive.', $targetPath));
				break;

			case \ZipArchive::ER_OPEN:
				throw new RuntimeException(sprintf('Update file %s cannot be opened.', $targetPath));
				break;

			case \ZipArchive::ER_READ:
				throw new RuntimeException(sprintf('Update file %s cannot be read from.', $targetPath));
				break;

			case \ZipArchive::ER_SEEK:
				throw new RuntimeException(sprintf('Update file %s cannot be skipped forward.', $targetPath));
				break;
		}

		return $zip->extractTo($targetPath);
	}

	// TODO Post-update code

	/**
	 * @return string
	 */
	public function getUpdateStreamUrl(): string
	{
		return $this->updateStreamUrl;
	}
}