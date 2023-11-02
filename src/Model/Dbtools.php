<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Document\Raw;
use Awf\Mvc\Model;

class Dbtools extends Model
{
	public function getFiles(): array
	{
		$path = APATH_CACHE . '/db_backups';

		if (!is_dir($path) || !is_readable($path))
		{
			return [];
		}

		$allFiles = [];

		$di = new \DirectoryIterator($path);

		/** @var \DirectoryIterator $item */
		foreach ($di as $item)
		{
			if ($item->isDot() || !$item->isFile() || !in_array($item->getExtension(), ['sql', 'gz']))
			{
				continue;
			}

			if (str_ends_with($item->getBasename(), '.gz') && !str_ends_with($item->getBasename(), '.sql.gz'))
			{
				continue;
			}

			$allFiles[] = (object) [
				'filename' => $item->getBasename(),
				'ctime'    => new \DateTime('@' . $item->getCTime()),
				'size'     => $item->getSize(),
			];
		}

		uasort($allFiles, fn($a, $b) => -1 * ($a->ctime <=> $b->ctime));

		return $allFiles;
	}

	public function deleteFile(string $fileName): bool
	{
		$path = APATH_CACHE . '/db_backups';
		$filePath = $path . '/' . $fileName;

		if (realpath($path) === false)
		{
			throw new \RuntimeException('The folder does not exist.');
		}

		// Nope. Only specific files can be deleted.
		if (!str_ends_with($fileName, '.sql') && !str_ends_with($fileName, '.sql.gz'))
		{
			throw new \RuntimeException("Some things in life are bad. They can really make you mad. Other things just make you swear and curse.");
		}

		// Are you really trying to pull a fast one on me? Naughty, naughty boy!
		if (realpath($path) !== realpath(dirname($filePath)))
		{
			throw new \RuntimeException('He\'s not the messiah! He\'s a very naughty boy!');
		}

		if (!is_file($filePath))
		{
			throw new \RuntimeException('File not found.');
		}

		return @unlink($filePath);
	}

	public function downloadFile(string $fileName): void
	{
		$path = APATH_CACHE . '/db_backups';
		$filePath = $path . '/' . $fileName;

		if (realpath($path) === false)
		{
			throw new \RuntimeException('The folder does not exist.');
		}

		// Nope. Only specific files can be deleted.
		if (!str_ends_with($fileName, '.sql') && !str_ends_with($fileName, '.sql.gz'))
		{
			throw new \RuntimeException("Some things in life are bad. They can really make you mad. Other things just make you swear and curse.");
		}

		// Are you really trying to pull a fast one on me? Naughty, naughty boy!
		if (realpath($path) !== realpath(dirname($filePath)))
		{
			throw new \RuntimeException('He\'s not the messiah! He\'s a very naughty boy!');
		}

		if (!is_file($filePath))
		{
			throw new \RuntimeException('File not found.');
		}

		$fileSize = @filesize($filePath);
		$mimeType = str_ends_with($fileName, '.gz') ? 'application/gzip' : 'text/plain; charset=utf-8';
		$disposition = sprintf('attachment; filename="%s"', $fileName);

		/** @var Raw $document */
		$document = $this->getContainer()->application->getDocument();
		$document->addHTTPHeader('Content-Length', $fileSize);
		$document->addHTTPHeader('Content-Disposition', $disposition);
		$document->setMimeType($mimeType);

		echo file_get_contents($filePath);
	}

}