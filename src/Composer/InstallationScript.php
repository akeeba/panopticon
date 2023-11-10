<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Composer;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Awf\Mvc\Model;
use Composer\Script\Event;
use Symfony\Component\Finder\Finder;
use function Symfony\Component\VarDumper\Dumper\esc;

abstract class InstallationScript
{
	public static function makeVersionPhp(Event $event): void
	{
		$io         = $event->getIO();
		$targetFile = __DIR__ . '/../../version.php';

		if (file_exists($targetFile))
		{
			$io->debug('version.php already exists; skipping');

			return;
		}

		$fileContents = file_get_contents(__DIR__ . '/../../build/templates/version.php');

		if ($fileContents === false)
		{
			$io->warning('Cannot find build/templates/version.php. Creating a new version.php file has failed.');
		}

		$workingCopyDir = realpath((__DIR__ . '/../..'));

		$replacements = [
			'##VERSION##' => self::getChangelogVersion($workingCopyDir)
			                 ?? self::getLatestGitTag($workingCopyDir)
			                    ?? self::getFakeVersion($workingCopyDir),
			'##DATE##'    => gmdate('Y-m-d'),
		];
		$fileContents = str_replace(array_keys($replacements), array_values($replacements), $fileContents);

		$result = file_put_contents($targetFile, $fileContents);

		if ($result)
		{
			$io->debug('Created a new version.php file');
		}
		else
		{
			$io->warning('Could not create a version.php file. Is the directory not writeable?');
		}
	}

	/**
	 * Run actions after Composer Update
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
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
	 *
	 * @since        1.0.0
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
	 * Compiles and minifies the JavaScript files
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public static function babel(Event $event): void
	{
		$io = $event->getIO();
		$io->debug('Compiling JavaScript files');

		$container = self::getAWFContainer();
		$extras    = $event->getComposer()->getPackage()->getExtra();

		foreach ($extras['babel'] ?? [] as $definition)
		{
			$folder  = trim($definition['folder'], '/');
			$outdir  = trim($definition['outdir'], '/');
			$names   = $definition['names'] ?? ['*.js'];
			$exclude = $definition['exclude'] ?? null;

			if (empty($folder) || empty($outdir))
			{
				continue;
			}

			$folder = $container->basePath . '/' . $folder;
			$outdir = $container->basePath . '/' . $outdir;

			$finder = new Finder();
			$finder->ignoreDotFiles(true)
				->ignoreVCS(true)
				->ignoreVCSIgnored(true)
				->in($folder)
				->name($names)
				->files();

			if ($exclude)
			{
				$finder->notName($exclude);
			}

			if (!$finder->hasResults())
			{
				continue;
			}

			foreach ($finder as $file)
			{
				$inFile  = $file->getPathname();
				$outFile = $file->getPath() . '/' . $file->getBasename('.js') . '.min.js';

				if (file_exists($outFile) && filemtime($outFile) >= filemtime($inFile))
				{
					continue;
				}

				$cwd = getcwd();
				chdir($container->basePath);

				$command = 'npx babel ' . escapeshellarg($inFile) . ' --out-dir ' . escapeshellarg($outdir)
					. ' --out-file-extension ' . escapeshellarg('.min.js') . ' --source-maps';

				passthru($command);

				chdir($cwd);
			}
		}
	}

	/**
	 * Compiles SCSS into minified CSS
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public static function sass(Event $event): void
	{
		$io = $event->getIO();
		$io->debug('Compiling SCSS files');

		$container = self::getAWFContainer();
		$extras    = $event->getComposer()->getPackage()->getExtra();

		foreach ($extras['sass'] ?? [] as $definition)
		{
			$folder  = trim($definition['folder'], '/');
			$outdir  = trim($definition['outdir'], '/');
			$names   = $definition['names'] ?? ['*.js'];
			$exclude = $definition['exclude'] ?? null;

			if (empty($folder) || empty($outdir))
			{
				continue;
			}

			$folder = $container->basePath . '/' . $folder;
			$outdir = $container->basePath . '/' . $outdir;

			$finder = new Finder();
			$finder->ignoreDotFiles(true)
				->ignoreVCS(true)
				->ignoreVCSIgnored(true)
				->in($folder)
				->name($names)
				->files();

			if ($exclude)
			{
				$finder->notName($exclude);
			}

			if (!$finder->hasResults())
			{
				continue;
			}

			foreach ($finder as $file)
			{
				$inFile  = $file->getPathname();
				$outFile = $outdir . '/' . $file->getBasename('.scss') . '.min.css';

				if (file_exists($outFile) && filemtime($outFile) >= filemtime($inFile))
				{
					continue;
				}

				$cwd = getcwd();
				chdir($container->basePath);

				$command = 'sass ' . escapeshellarg($inFile . ':' . $outFile) .
					' -s compressed --update';

				passthru($command);

				chdir($cwd);
			}
		}
	}

	public static function schemaUpdate(Event $event): void
	{
		$container = self::getAWFContainer();
		$appConfig = $container->appConfig;

		if (!BootstrapUtilities::hasConfiguration())
		{
			return;
		}

		$appConfig->loadConfiguration();

		/** @var \Akeeba\Panopticon\Model\Setup $model */
		$model = $container->mvcFactory->makeTempModel('Setup');
		// Check the installed default tasks
		$model->checkDefaultTasks();
		// Make sure the DB tables are installed correctly
		$model->installDatabase();
	}

	public static function copyPackageLock(Event $event)
	{
		$io = $event->getIO();
		$io->debug('Copying package-lock.json');

		$container = self::getAWFContainer();

		$container->fileSystem->copy(
			$container->basePath . '/package-lock.json',
			$container->basePath . '/vendor/composer/package-lock.json'
		);

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

	private static function getChangelogVersion(string $changeLogDirectory): ?string
	{
		// Try to detect the CHANGELOG file
		$rootDir    = rtrim($changeLogDirectory, '/' . DIRECTORY_SEPARATOR);
		$changeLogs = [
			'CHANGELOG',
			'CHANGELOG.md',
			'CHANGELOG.php',
			'CHANGELOG.txt',
		];

		foreach ($changeLogs as $possibleFile)
		{
			$possibleFile = $rootDir . '/' . $possibleFile;

			if (@file_exists($possibleFile))
			{
				$changelog = $possibleFile;
			}
		}

		// No changelog specified? Bummer.
		if (empty($changelog ?? null))
		{
			return null;
		}

		// Get the contents of the changelog.
		$content = @file_get_contents($changelog);

		if (empty($content))
		{
			return null;
		}

		// Remove a leading die() statement
		$lines = array_map('trim', explode("\n", $content));

		if (strpos($lines[0], '<?') !== false)
		{
			array_shift($lines);
		}

		// Remove empty lines
		$lines = array_filter($lines, function ($x) {
			return !empty($x);
		});

		// The first line should be "Something something something VERSION" or just "VERSION"
		$firstLine = array_shift($lines);
		$parts     = explode(' ', $firstLine);
		$firstLine = array_pop($parts);

		// The first line should be "Something something something VERSION" or just "VERSION"

		if (!preg_match('/((\d+\.?)+)(((a|alpha|b|beta|rc|dev)\d)*(-[^\s]*)?)?/', $firstLine, $matches))
		{
			return null;
		}

		$version = $matches[0];

		if (is_array($version))
		{
			$version = array_shift($version);
		}

		return $version;
	}

	private static function getLatestGitTag(string $workingCopy): ?string
	{
		if ($workingCopy == '..')
		{
			$workingCopy = '../';
		}

		$cwd         = getcwd();
		$workingCopy = realpath($workingCopy);

		chdir($workingCopy);
		exec('git describe --abbrev=0 --tags', $out);
		chdir($cwd);

		if (empty($out))
		{
			return null;
		}

		return ltrim(trim($out[0]), 'v.');
	}

	private static function getFakeVersion(string $workingCopy): string
	{
		$commitHash = self::getLatestCommitHash($workingCopy);

		return '0.0.0-dev' . gmdate('YmdHi') . (empty($commitHash) ? '' : ('-rev' . $commitHash));
	}

	private static function getLatestCommitHash(string $workingCopy): string
	{
		if ($workingCopy == '..')
		{
			$workingCopy = '../';
		}

		$cwd         = getcwd();
		$workingCopy = realpath($workingCopy);

		chdir($workingCopy);
		exec('git log --format=%h -n1', $out);
		chdir($cwd);

		return empty($out) ? '' : trim($out[0]);
	}

}