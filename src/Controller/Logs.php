<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Log;
use Awf\Mvc\Controller;

/**
 * Log management controller
 *
 * @since  1.0.0
 */
class Logs extends Controller
{
	/**
	 * Main page: list the log files, paginated
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function main()
	{
		$this->display();
	}

	/**
	 * Read a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function read(): void
	{
		$format = $this->input->get('format', 'html');

		if ($format !== 'html')
		{
			$this->csrfProtection();
		}

		$view = $this->getView();
		$view->setLayout('item' . ($format === 'raw' ? '_table' : ''));

		$this->display();
	}

	/**
	 * Delete a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function delete(): void
	{
		$this->csrfProtection();

		/** @var Log $model */
		$model     = $this->getModel();
		$filePath  = $model->getVerifiedLogFilePath();
		$returnUrl = $this->container->router->route(sprintf('index.php?view=log'));

		if (empty($filePath))
		{
			$this->setRedirect($returnUrl, $this->getLanguage()->text('PANOPTICON_LOGS_LBL_NOT_DELETED'), 'error');

			return;
		}

		if (!$this->container->fileSystem->delete($filePath))
		{
			@unlink($this->container->fileSystem->delete($filePath));
		}

		$this->setRedirect(
			$returnUrl,
			$this->getLanguage()->sprintf('PANOPTICON_LOGS_LBL_DELETED', basename($filePath)),
			'success'
		);
	}

	/**
	 * Download a log file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function download(): void
	{
		$this->csrfProtection();

		/** @var Log $model */
		$model     = $this->getModel();
		$filePath  = $model->getVerifiedLogFilePath();
		$returnUrl = $this->container->router->route(sprintf('index.php?view=log'));

		if (empty($filePath))
		{
			$this->setRedirect($returnUrl, $this->getLanguage()->text('PANOPTICON_LOGS_LBL_CANNOT_DOWNLOAD'), 'error');

			return;
		}

		$mimeType = str_ends_with($filePath, '.gz') ? 'application/gzip' : 'text/plain';
		$fileName = str_ends_with($filePath, '.gz') ? basename($filePath) : (substr(basename($filePath), 0, -3) . 'txt');
		$fileSize = @filesize($filePath);

		// Clear output cache
		/** @noinspection PhpStatementHasEmptyBodyInspection */
		while (@ob_end_clean())
		{
			// Make sure no junk will come before our content – to the extent we have a say on this...
		}

		if (function_exists('ini_get') &&
		    function_exists('ini_set') &&
		    ini_get('zlib.output_compression'))
		{
			ini_set('zlib.output_compression', 'Off');
		}

		@clearstatcache();

		// Disable caching
		header("Cache-Control: no-store, max-age=0, must-revalidate, no-transform", true);

		// Send MIME headers
		header('Content-Type: ' . $mimeType);
		header("Accept-Ranges: bytes");
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Transfer-Encoding: binary');
		header('Connection: close');

		error_reporting(0);
		set_time_limit(0);

		// Support resumable downloads
		$isResumable = false;
		$seek_start  = 0;
		$seek_end    = $fileSize - 1;

		$range = $this->input->server->get('HTTP_RANGE', null, 'raw');

		if (!is_null($range) || (trim($range) === ''))
		{
			[$size_unit, $range_orig] = explode('=', $range, 2);

			if ($size_unit == 'bytes')
			{
				//multiple ranges could be specified at the same time, but for simplicity only serve the first range
				//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
				/** @noinspection PhpUnusedLocalVariableInspection */
				[$range, $extra_ranges] = explode(',', $range_orig, 2);
			}
			else
			{
				$range = '';
			}
		}
		else
		{
			$range = '';
		}

		if ($range)
		{
			// Figure out download piece from range (if set)
			[$seek_start, $seek_end] = explode('-', $range, 2);

			// Set start and end based on range (if set), else set defaults. Also checks for invalid ranges.
			$seek_end   = (empty($seek_end)) ? ($fileSize - 1) : min(abs(intval($seek_end)), ($fileSize - 1));
			$seek_start =
				(empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

			$isResumable = true;
		}

		// Use 1M chunks for echoing the data to the browser
		$chunkSize = 1024 * 1024;
		$handle    = @fopen($filePath, 'r');

		if ($handle === false)
		{
			// Notify of filesize, if this info is available
			if ($fileSize > 0)
			{
				header('Content-Length: ' . (int) $fileSize);
			}

			@readfile($filePath);
		}
		else
		{
			$totalLength = 0;

			if ($isResumable)
			{
				//Only send partial content header if downloading a piece of the file (IE workaround)
				if ($seek_start > 0 || $seek_end < ($fileSize - 1))
				{
					header('HTTP/1.1 206 Partial Content');
				}

				// Necessary headers
				$totalLength = $seek_end - $seek_start + 1;

				header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $fileSize);
				header('Content-Length: ' . $totalLength);

				// Seek to start
				fseek($handle, $seek_start);
			}
			else
			{
				$isResumable = false;

				// Notify of filesize, if this info is available
				if ($fileSize > 0)
				{
					header('Content-Length: ' . (int) $fileSize);
				}
			}

			$read = 0;

			while (!feof($handle) && ($chunkSize > 0))
			{
				if ($isResumable && ($totalLength - $read < $chunkSize))
				{
					$chunkSize = $totalLength - $read;

					if ($chunkSize < 0)
					{
						continue;
					}
				}

				$buffer = fread($handle, $chunkSize);

				if ($isResumable)
				{
					$read += strlen($buffer);
				}

				echo $buffer;

				@ob_flush();
				flush();
			}

			@fclose($handle);
		}

		$this->container->application->close();
	}
}
