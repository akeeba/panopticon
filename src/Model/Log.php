<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Exception\App;
use Awf\Mvc\Model;
use Awf\Pagination\Pagination;
use DirectoryIterator;
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
	public function getLogLines(): array
	{
		// Clamp the size in the range of 10KiB to 10MiB
		$maxSize = $this->getState('size', 131072, 'int');
		$maxSize = max(10240, min($maxSize, 10485760));

		// Clamp the lines in the range of 20 to 10000
		$maxLines = $this->getState('lines', 500, 'int');
		$maxLines = max(20, min($maxLines, 10000));

		// Make sure the log file exists, it is readable, and it does not traverse to a folder other than the log folder
		$logFile = APATH_LOG . '/' . $this->getState('logfile', '');

		if (!file_exists($logFile) || !is_readable($logFile))
		{
			return [];
		}

		$logFile = realpath($logFile);

		if (dirname($logFile) !== realpath(APATH_LOG))
		{
			return [];
		}

		// Return the parsed log lines
		return $this->getLastLogLines($logFile, $maxSize, $maxLines);
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
		return $lines;
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

		$logLevel = match (strtolower($parts[1]))
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

		$context = null;
		$extra   = null;

		if (count($parts) >= 5)
		{
			try
			{
				$context = json_decode($parts[4], flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$context = null;
			}
		}

		if (count($parts) >= 6)
		{
			try
			{
				$extra = json_decode($parts[5], flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$extra = null;
			}
		}

		return (object) [
			'loglevel'  => $logLevel,
			'timestamp' => $timestamp,
			'facility'  => $parts[2],
			'message'   => $parts[3],
			'context'   => $context,
			'extra'     => $extra,
		];
	}
}