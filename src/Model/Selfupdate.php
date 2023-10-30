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

/**
 * Self-update (integrated update) Model
 *
 * @since  1.0.0
 */
class Selfupdate extends Model
{
	/**
	 * Third-party dependency files which need to be removed before extracting the update package.
	 *
	 * This can also be used to remove files which, when present after the update file's extraction, would cause the
	 * application to fail.
	 */
	private const DEPS_FILES = [];

	/**
	 * Folders with third-party dependencies which need to be removed before extracting the update package.
	 *
	 * The idea is that the contents of these folders may vary wildly from one version of Panopticon to the next, and we
	 * do not want to have to figure out how to track the individual files and folders which need to be deleted.
	 *
	 * This can also be used to remove folders which, when present after the update file's extraction, would cause the
	 * application to fail.
	 */
	private const DEPS_FOLDERS = [
		'vendor',
		'media/ace',
		'media/choices',
		'media/tinymce',
	];

	/**
	 * Old files to delete AFTER the update is finished.
	 *
	 * The application must not break when these files are present.
	 */
	private const REMOVE_FILES = [
		// Obsolete file
		'phpinfo.php'
	];

	/**
	 * Old folders to delete AFTER the update is finished.
	 *
	 * The application must not break when these folders are present.
	 */
	private const REMOVE_FOLDERS = [
	];

	/**
	 * @var string The currently installed version
	 */
	private string $currentVersion;

	/**
	 * @var string The URL of the update stream for this software
	 */
	private string $updateStreamUrl;

	public function __construct(Container $container = null)
	{
		parent::__construct($container);

		$this->currentVersion  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';
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
			pool: $this->container->cacheFactory->pool('system')
		);

		return $cacheController->get(function (): UpdateInformation {
			/** @var Client $httpClient */
			$httpClient = $this->container->httpFactory->makeClient(cache: false);
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
		$options    = $this->container->httpFactory->getDefaultRequestOptions();

		$options[RequestOptions::TIMEOUT] = 180.0;

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

	/**
	 * Extracts an update package
	 *
	 * @param   string|null  $sourceFile  The update package. Default: <temp_folder>/update.zip
	 * @param   string       $targetPath  The path to extract the update to. Default: APATH_ROOT
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function extract(?string $sourceFile = null, string $targetPath = APATH_ROOT): bool
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

		// Set obscenely large limits to prevent timeout or memory exhaustion from breaking the update
		if (function_exists('ini_set'))
		{
			ini_set('max_execution_time', 3600);
			ini_set('memory_limit', '1024M');
		}

		$zip = new \ZipArchive();

		switch ($zip->open($sourceFile, \ZipArchive::RDONLY))
		{
			case \ZipArchive::ER_INCONS:
				/**
				 * Ignore this. Despite open() returning this error it can still open and extract the archive. Yeah.
				 */
				// throw new RuntimeException(sprintf('Update file %s is inconsistent.', $sourceFile));
				break;

			case \ZipArchive::ER_INVAL:
			case \ZipArchive::ER_MEMORY:
				throw new RuntimeException(sprintf('Cannot open update file %s: internal error in PHP', $sourceFile));
				break;

			case \ZipArchive::ER_NOENT:
				throw new RuntimeException(sprintf('Update file %s does not exist.', $sourceFile));
				break;

			case \ZipArchive::ER_NOZIP:
				throw new RuntimeException(sprintf('Update file %s is not a ZIP archive.', $sourceFile));
				break;

			case \ZipArchive::ER_OPEN:
				throw new RuntimeException(sprintf('Update file %s cannot be opened.', $sourceFile));
				break;

			case \ZipArchive::ER_READ:
				throw new RuntimeException(sprintf('Update file %s cannot be read from.', $sourceFile));
				break;

			case \ZipArchive::ER_SEEK:
				throw new RuntimeException(sprintf('Update file %s cannot be skipped forward.', $sourceFile));
				break;
		}

		/**
		 * Before extracting, delete the third party dependency and critical folders and files (if they exist).
		 */
		foreach (self::DEPS_FILES as $file)
		{
			$file = $this->container->basePath . '/' . $file;

			if (!file_exists($file))
			{
				continue;
			}

			$this->container->fileSystem->delete($file);
		}

		foreach (self::DEPS_FOLDERS as $folder)
		{
			$folder = $this->container->basePath . '/' . $folder;

			if (!file_exists($folder) || !is_dir($folder))
			{
				continue;
			}

			$this->container->fileSystem->rmdir($folder);
		}

		/**
		 * Extract the ZIP file.
		 */
		$result = $zip->extractTo($targetPath);

		$zip->close();

		return $result;
	}

	/**
	 * Executes after an update package has been extracted
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function postUpdate()
	{
		/** @var Container $container */
		$container = $this->getContainer();

		/** @var \Akeeba\Panopticon\Model\Setup $model */
		$model = $this->getModel('Setup');
		// Check the installed default tasks
		$model->checkDefaultTasks();
		// Make sure the DB tables are installed correctly
		$model->installDatabase();

		// Remove old files and folders
		foreach (self::REMOVE_FILES as $file)
		{
			$file = $this->container->basePath . '/' . $file;

			if (!file_exists($file))
			{
				continue;
			}

			$this->container->fileSystem->delete($file);
		}

		foreach (self::REMOVE_FOLDERS as $folder)
		{
			$folder = $this->container->basePath . '/' . $folder;

			if (!file_exists($folder) || !is_dir($folder))
			{
				continue;
			}

			$this->container->fileSystem->rmdir($folder);
		}

		// Remove obsolete cache pools
		$container->cacheFactory->pool('php_versions')->clear();
		$container->cacheFactory->pool('self_update')->clear();

		// Finally, force–reload the update information
		$this->getUpdateInformation(true);
	}

	/**
	 * @return string
	 */
	public function getUpdateStreamUrl(): string
	{
		return $this->updateStreamUrl;
	}
}