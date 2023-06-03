<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Composer;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Composer\Script\Event;
use Symfony\Component\Finder\Finder;

abstract class InstallationScript
{
	/**
	 * Run actions after Composer Update
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	public static function postComposerUpdate(Event $event): void
	{
		self::emptyReleaseDirectory($event);
		self::copyHtaccessIntoVendor($event);
		self::removeDSStore($event);
	}

	/**
	 * Copies static files pulled in via NPM to their respective locations
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	public static function copyNodeDependencies(Event $event): void
	{
		$io = $event->getIO();
		$io->debug('Copying static files');

		$container = self::getAWFContainer();

		$extras = $event->getComposer()->getPackage()->getExtra();

		foreach ($extras['copy-static'] ?? [] as $copyDef)
		{
			$type  = $copyDef['type'] ?? 'file';
			$from  = $copyDef['from'] ?? '';
			$to    = $copyDef['to'] ?? '';
			$names = $copyDef['names'] ?? null;

			$from = $container->basePath . '/' . trim($from, '/');
			$to   = $container->basePath . '/' . trim($to, '/');

			if (empty($from) || empty($to))
			{
				continue;
			}

			if ($type === 'file')
			{
				$container->fileSystem->copy($from, $to);

				continue;
			}

			self::copyFiles($from, $to, $names);
		}
	}

	/**
	 * Get the Container object of the application
	 *
	 * @return  Container
	 */
	private static function getAWFContainer(): Container
	{
		defined('AKEEBA') || define('AKEEBA', 1);

		require_once __DIR__ . '/../../defines.php';
		@include_once __DIR__ . '/../../version.php';

		defined('AKEEBA_PANOPTICON_VERSION') || define('AKEEBA_PANOPTICON_VERSION', '0.0.0-dev-installing');
		defined('AKEEBA_PANOPTICON_DATE') || define('AKEEBA_PANOPTICON_DATE', gmdate('Y-m-d'));
		defined('AKEEBA_PANOPTICON_CODENAME') || define('AKEEBA_PANOPTICON_CODENAME', 'Aphelion');
		defined('AKEEBA_PANOPTICON_MINPHP') || define('AKEEBA_PANOPTICON_MINPHP', PHP_VERSION);

		return Factory::getContainer();
	}

	private static function emptyReleaseDirectory(Event $event): void
	{
		$io = $event->getIO();
		$io->debug('Emptying the release directory');

		$container = self::getAWFContainer();

		$container->fileSystem->rmdir($container->basePath . '/release');
		$container->fileSystem->mkdir($container->basePath . '/release');
	}

	private static function copyHtaccessIntoVendor(Event $event)
	{
		$io = $event->getIO();
		$io->debug('Copying .htaccess and web.config into the vendor folder');

		$container = self::getAWFContainer();

		$container->fileSystem->copy(
			$container->basePath . '/src/.htaccess',
			$container->basePath . '/vendor/.htaccess'
		);
		$container->fileSystem->copy(
			$container->basePath . '/src/web.config',
			$container->basePath . '/vendor/web.config'
		);
	}

	private static function removeDSStore(Event $event)
	{
		$io = $event->getIO();
		$io->debug('Removing .DS_Store files');

		$container = self::getAWFContainer();

		$finder = new Finder();
		$finder->ignoreDotFiles(false)
			->ignoreVCS(true)
			->ignoreVCSIgnored(true)
			->in([
				$container->basePath . '/assets',
				$container->basePath . '/build',
				$container->basePath . '/cache',
				$container->basePath . '/cli',
				$container->basePath . '/includes',
				$container->basePath . '/languages',
				$container->basePath . '/log',
				$container->basePath . '/media',
				$container->basePath . '/src',
				$container->basePath . '/templates',
				$container->basePath . '/user_code',
				$container->basePath . '/ViewTemplates',
			])
			->name('.DS_Store')
			->files();

		if (!$finder->hasResults())
		{
			return;
		}

		foreach ($finder as $item)
		{
			$container->fileSystem->delete($item->getPathname());
		}
	}

	private static function copyFiles(string $from, string $to, ?array $names = null, ?callable $customiser = null)
	{
		$container = self::getAWFContainer();

		$finder = new Finder();
		$finder->ignoreDotFiles(true)
			->ignoreVCS(true)
			->ignoreVCSIgnored(true)
			->in($from)
			->files();

		if (!empty($names))
		{
			$finder->name($names);
		}

		if (is_callable($customiser))
		{
			call_user_func($customiser, $finder);
		}

		if (!$finder->hasResults())
		{
			return;
		}

		foreach ($finder as $file)
		{
			$target = rtrim($to, '/') . '/' . $file->getRelativePathname();

			$targetPath = dirname($target);

			if (!file_exists($targetPath))
			{
				$container->fileSystem->mkdir($targetPath);
			}

			$container->fileSystem->copy($file->getPathname(), $target);
		}
	}
}