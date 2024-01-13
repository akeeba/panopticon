<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Trait\FormatFilesizeTrait;
use Awf\Exception\App;
use Awf\Mvc\Model;
use Awf\Pagination\Pagination;
use DirectoryIterator;
use Exception;
use JsonException;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Log management model
 *
 * @since  1.0.0
 */
class Log extends Model
{
	use FormatFilesizeTrait;

	private array|null $logs = null;

	/**
	 * Get a paginated list of the log files
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public function getPaginatedLogFiles(): array
	{
		$limitStart = $this->getState('limitstart', 0, 'int');
		$limit      = $this->getState('limit', 50, 'int') ?? 50;

		return array_slice($this->getFilteredLogFilesList(), $limitStart, $limit);
	}

	/**
	 * Get the log pagination object
	 *
	 * @return  Pagination
	 * @throws  App
	 * @since   1.0.0
	 */
	public function getPagination(): Pagination
	{
		$limitStart = $this->getState('limitstart', 0);
		$limit      = $this->getState('limit', 50) ?? 50;
		$total      = count($this->getFilteredLogFilesList());

		return new Pagination($total, $limitStart, $limit, 10, $this->container);
	}

	/**
	 * Get the parsed log lines of the currently selected file.
	 *
	 * @return  object[]
	 * @since   1.0.0
	 */
	public function getLogLines(?string $logFile = null): array
	{
		// Clamp the size in the range of 10KiB to 10MiB
		$maxSize = $this->getState('size', 131072, 'int');
		$maxSize = max(10240, min($maxSize, 10485760));

		// Clamp the lines in the range of 20 to 10000
		$maxLines = $this->getState('lines', 500, 'int');
		$maxLines = max(20, min($maxLines, 10000));

		// Make sure the log file exists, it is readable, and it does not traverse to a folder other than the log folder
		$logFile ??= $this->getState('logfile', '');
		$logPath = $this->getVerifiedLogFilePath($logFile);

		if (empty($logPath))
		{
			return [];
		}

		// Return the parsed log lines
		return $this->getLastLogLines($logPath, $maxSize, $maxLines);
	}

	/**
	 * Get the size of a log file
	 *
	 * @param   string|null  $fileName  The log filename, NULL to get from the model state
	 *
	 * @return  int
	 * @since   1.0.0
	 */
	public function getFilesize(?string $fileName = null): int
	{
		$fileName ??= $this->getState('logfile', '');
		$filePath = $this->getVerifiedLogFilePath($fileName);

		if (!$filePath)
		{
			return 0;
		}

		$filePath = APATH_LOG . '/' . $fileName;

		try
		{
			$fileSize = @fileSize($filePath) ?? 0;
		}
		catch (Exception $e)
		{
			$fileSize = 0;
		}

		return $fileSize;
	}

	/**
	 * Returns a verified to be correct log file path given a log file name.
	 *
	 * This method performs the following checks:
	 * - The log file name must end in .log or .log.{number}.gz
	 * - It must be a filename relative to APATH_LOG
	 * - It must NOT end up pointing to a location outside APATH_LOG
	 * - The file must exist and be readable
	 *
	 * @param   string|null  $logFile  The name of the log file, NULL to get from the model state
	 *
	 * @return  string|null
	 * @since   1.0.0
	 */
	public function getVerifiedLogFilePath(?string $logFile = null): ?string
	{
		$logFile ??= $this->getState('logfile', '');

		if (!str_ends_with($logFile, '.log') && !str_ends_with($logFile, '.gz')
		    && !preg_match(
				'/\.log\.\d+\.gz$/', $logFile
			))
		{
			return null;
		}

		$logPath = APATH_LOG . '/' . $logFile;

		if (!file_exists($logPath) || !is_readable($logPath))
		{
			return null;
		}

		$logPath = realpath($logPath);

		if (dirname($logPath) !== realpath(APATH_LOG))
		{
			return null;
		}

		return $logPath;
	}

	/**
	 * Returns the filtered, non-paginated list of log files
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	protected function getFilteredLogFilesList(): array
	{
		if ($this->logs !== null)
		{
			return $this->logs;
		}

		$search     = $this->getState('search');
		$archived   = ($this->getState('archived', 1) ?? 1) == 1;
		$this->logs = $this->listLogFiles($archived);

		if (!empty($search))
		{
			$this->logs = array_filter(
				$this->logs, fn($x) => str_contains($x, $search)
			);
		}

		$siteId = (int) $this->getState('site_id', '0');

		if ($siteId > 0)
		{
			$this->logs = array_filter(
				$this->logs, fn($x) => str_ends_with($x, '.' . $siteId . '.log') || str_contains($x, '.' . $siteId . '.log.')
			);
		}

		return $this->logs;
	}

	/**
	 * Returns the raw list of all available log files
	 *
	 * @param   bool  $includeArchived  Should I include archived logs as well?
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	private function listLogFiles(bool $includeArchived = true): array
	{
		$ret = [];

		/** @var DirectoryIterator $file */
		foreach (new DirectoryIterator(APATH_LOG) as $file)
		{
			if ($file->isDot() || !$file->isFile() || !in_array($file->getExtension(), ['log', 'gz']))
			{
				continue;
			}

			if (!$includeArchived && $file->getExtension() === 'gz')
			{
				continue;
			}

			$ret[] = $file->getFilename();
		}

		sort($ret, SORT_NATURAL);

		return $ret;
	}

	/**
	 * Returns the last few lines of a log file
	 *
	 * @param   string  $logPath   The full filesystem path of the log file to open
	 * @param   int     $maxSize   The maximum size (in bytes) of log data to return
	 * @param   int     $maxLines  The maximum number of log lines to return
	 *
	 * @return  array<object>
	 * @since   1.0.0
	 */
	private function getLastLogLines(string $logPath, int $maxSize = 131072, int $maxLines = 500): array
	{
		$fp = @fopen($logPath, 'rt');

		if ($fp === false)
		{
			return [];
		}

		@fseek($fp, 0, SEEK_END);
		$totalSize = ftell($fp);

		if ($totalSize < 10)
		{
			@fclose($fp);

			return [];
		}

		$walkBack = min($totalSize, $maxSize);

		fseek($fp, -$walkBack, SEEK_CUR);

		$data = @fread($fp, $maxSize);

		fclose($fp);

		$lines = explode("\n", $data);
		$lines = array_filter($lines);

		// Throw away the first line, it might be incomplete
		array_shift($lines);

		// Parse the log lines
		$lines = array_map([$this, 'parseLogLine'], $lines);
		$lines = array_filter($lines);

		// Limit the number of lines displayed
		if (count($lines) > $maxLines)
		{
			$lines = array_slice($lines, -$maxLines);
		}

		// Returned the parsed lines
		return array_reverse($lines);
	}

	/**
	 * Parses a log line into an object
	 *
	 * @param   string|null  $logLine  The log line to parse
	 *
	 * @return  null|object  The parsed log line, or NULL if it has an invalid format
	 */
	private function parseLogLine(?string $logLine): ?object
	{
		if (!str_contains($logLine, '|'))
		{
			return null;
		}

		$parts = explode('|', $logLine);

		if (count($parts) < 4)
		{
			return null;
		}

		try
		{
			$timestamp = $this->container->dateFactory($parts[0]);
		}
		catch (Throwable)
		{
			return null;
		}

		$logLevel = match (trim(strtolower($parts[1])))
		{
			'emergency' => LogLevel::EMERGENCY,
			'alert' => LogLevel::ALERT,
			'critical' => LogLevel::CRITICAL,
			'error' => LogLevel::ERROR,
			'warning' => LogLevel::WARNING,
			'notice' => LogLevel::NOTICE,
			'info' => LogLevel::INFO,
			'debug' => LogLevel::DEBUG,
			default => null
		};

		if (empty($logLevel))
		{
			return null;
		}

		$short    = false;
		$facility = $parts[2];
		$message  = $parts[3];

		if (count($parts) < 6)
		{
			$short    = true;
			$facility = '';
			$message  = $parts[2];
		}

		$context = null;
		$extra   = null;

		if (isset($parts[$short ? 3 : 4]))
		{
			try
			{
				$context = json_decode($parts[$short ? 3 : 4], flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$context = null;
			}
		}

		if (isset($parts[$short ? 4 : 5]))
		{
			try
			{
				$extra = json_decode($parts[$short ? 4 : 5], flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$extra = null;
			}
		}

		return (object) [
			'loglevel'  => $logLevel,
			'timestamp' => $timestamp,
			'facility'  => trim($facility),
			'message'   => trim($message),
			'context'   => $context,
			'extra'     => $extra,
		];
	}
}