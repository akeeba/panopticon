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
	public static function postComposerUpdate(Event $event)
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
	 */
	public static function copyNodeDependencies(Event $event)
	{
		self::copyBootstrapJavaScript($event);
		self::copyFontAwesome($event);
		self::copyTinyMCE($event);
		self::copyACEEditor($event);
		self::copyChoicesJS($event);
	}

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

	private static function copyBootstrapJavaScript(Event $event)
	{
		$io = $event->getIO();
		$io->debug('Copying Bootstrap JavaScript files');

		$container = self::getAWFContainer();

		self::copyFiles(
			$container->basePath . '/node_modules/bootstrap/dist/js',
			$container->basePath . '/media/js/',
			[
				'bootstrap.bundle.*'
			]
		);
	}

	private static function copyFontAwesome(Event $event) {
		$io = $event->getIO();
		$io->debug('Copying FontAwesome files');

		$container = self::getAWFContainer();

		$container->fileSystem->copy(
			$container->basePath . '/node_modules/@fortawesome/fontawesome-free/css/all.css',
			$container->basePath . '/media/css/fontawesome.css',
		);
		$container->fileSystem->copy(
			$container->basePath . '/node_modules/@fortawesome/fontawesome-free/css/all.min.css',
			$container->basePath . '/media/css/fontawesome.min.css',
		);

		self::copyFiles(
			$container->basePath . '/node_modules/@fortawesome/fontawesome-free/webfonts',
			$container->basePath . '/media/webfonts',
			[
				'*.woff2'
			]
		);
	}

	private static function copyTinyMCE(Event $event)
	{
		$io = $event->getIO();
		$io->debug('Copying TinyMCE files');

		$container = self::getAWFContainer();

		self::copyFiles(
			$container->basePath . '/node_modules/tinymce/icons',
			$container->basePath . '/media/tinymce/icons'
		);
		self::copyFiles(
			$container->basePath . '/node_modules/tinymce/models',
			$container->basePath . '/media/tinymce/models'
		);
		self::copyFiles(
			$container->basePath . '/node_modules/tinymce/plugins',
			$container->basePath . '/media/tinymce/plugins'
		);
		self::copyFiles(
			$container->basePath . '/node_modules/tinymce/skins',
			$container->basePath . '/media/tinymce/skins'
		);
		self::copyFiles(
			$container->basePath . '/node_modules/tinymce/themes',
			$container->basePath . '/media/tinymce/themes'
		);
		self::copyFiles(
			$container->basePath . '/node_modules/tinymce',
			$container->basePath . '/media/tinymce',
			[
				'tinymce.js',
				'tinymce.min.js',
			]
		);
	}

	private static function copyACEEditor(Event $event) {
		$io = $event->getIO();
		$io->debug('Copying Cloud9 ACE editor files');

		$container = self::getAWFContainer();

		self::copyFiles(
			$container->basePath . '/node_modules/ace-builds/css',
			$container->basePath . '/media/ace/css',
			[
				'ace.css',
				'dracula*.png',
				'github*.png',
				'main*.png'
			]
		);
		self::copyFiles(
			$container->basePath . '/node_modules/ace-builds/css/theme',
			$container->basePath . '/media/ace/css/theme',
			[
				'dracula.css',
				'github.css',
			]
		);
		self::copyFiles(
			$container->basePath . '/node_modules/ace-builds/src',
			$container->basePath . '/media/ace',
			[
				'ace*.js',
				'ext-searchbox.js',
				'ext-language_tools.js',
				'mode-css.*',
				'mode-html.*',
				'mode-plain_text.*',
				// 'mode-php.*',
				// 'mode-php_laravel_blade.*',
				'theme-dracula.*',
				'theme-github.*',
				'worker-base.*',
				'worker-css.*',
				'worker-html.*',
				//'worker-php.*',
			]
		);
	}

	private static function copyChoicesJS(Event $event) {
		$io = $event->getIO();
		$io->debug('Copying Choices.js files');

		$container = self::getAWFContainer();

		self::copyFiles(
			$container->basePath . '/node_modules/choices.js/public/assets/scripts',
			$container->basePath . '/media/choices',
			[
				'choices.js',
				'choices.min.js',
			]
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