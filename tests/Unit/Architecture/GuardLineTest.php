<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Architecture;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Asserts that every shipped PHP file refuses to run when requested directly over the web.
 *
 * Only `index.php` is meant to be reachable from a browser. It defines the `AKEEBA` constant; every other PHP file
 * starts with `defined('AKEEBA') || die;` so that requesting it directly stops before doing anything. Without the
 * guard, a direct request runs the file out of context: it typically dies on an undefined variable or class, and when
 * PHP errors are displayed the error message reveals the server's filesystem path.
 *
 * The sample `htaccess.txt` blocks these folders on Apache, but that only works on Apache with `AllowOverride` enabled
 * and the sample file actually renamed. NginX, IIS without the matching rules, and Apache with `AllowOverride None` all
 * serve the files. The guard line is the defence which works everywhere.
 *
 * @since 2.4.0
 */
class GuardLineTest extends AbstractUnitTestCase
{
	/**
	 * Directories scanned recursively, relative to the repository root. Everything in them is shipped.
	 */
	private const SCANNED_DIRECTORIES = ['cli', 'includes', 'src', 'templates', 'ViewTemplates'];

	/**
	 * Individual shipped files outside the scanned directories, relative to the repository root.
	 *
	 * `build/templates/version.php` is the template for the `version.php` file in the release package's root.
	 */
	private const EXTRA_FILES = ['defines.php', 'build/templates/version.php'];

	/**
	 * Files which legitimately have no guard line, relative to the repository root, with the reason.
	 *
	 * Keep this list short and every reason concrete. A file belongs here only if it necessarily runs before the
	 * `AKEEBA` constant can be defined, or if it is the thing defining it.
	 */
	private const EXEMPT = [
		'index.php'                           => 'Web entry point; it defines AKEEBA.',
		'cli/panopticon.php'                  => 'CLI entry point; it defines AKEEBA, and refuses any non-CLI SAPI.',
		'src/Composer/InstallationScript.php' => 'Composer autoloads it to run Composer scripts, before AKEEBA exists.',
	];

	/**
	 * Directories which must carry the deny-all `.htaccess` and `web.config` files, relative to the repository root.
	 *
	 * They are a second layer on Apache (with AllowOverride) and IIS. Nothing in them is ever served directly.
	 */
	private const DENIED_DIRECTORIES = [
		'cache', 'includes', 'languages', 'log', 'src', 'templates', 'tmp', 'ViewTemplates',
	];

	/**
	 * Does this PHP source start with the guard line?
	 *
	 * The guard must be in the file's first PHP block, so that it runs before anything is output, and it must be a
	 * statement of its own at the start of a line — not a commented-out copy.
	 *
	 * @param   string  $code  The PHP source to examine.
	 *
	 * @return  bool
	 */
	private static function hasGuard(string $code): bool
	{
		if (!str_starts_with(ltrim(preg_replace('/^#![^\n]*\n/', '', $code)), '<?php'))
		{
			return false;
		}

		$closeTag   = strpos($code, '?>');
		$firstBlock = $closeTag === false ? $code : substr($code, 0, $closeTag);

		return (bool) preg_match(
			'/^(?:<\?php)?[ \t]*defined\s*\(\s*([\'"])AKEEBA\1\s*\)\s*(?:\|\||or)\s*die\s*(?:\(\s*\))?\s*;/mi',
			$firstBlock
		);
	}

	/**
	 * @return  string  The repository root.
	 */
	private static function root(): string
	{
		return realpath(__DIR__ . '/../../..');
	}

	/**
	 * @return  array<string>  Every shipped PHP file in scope, as a repository-relative path.
	 */
	private static function shippedFiles(): array
	{
		$root  = self::root();
		$files = [];

		foreach (self::SCANNED_DIRECTORIES as $directory)
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
			);

			/** @var SplFileInfo $file */
			foreach ($iterator as $file)
			{
				if (!$file->isFile() || $file->getExtension() !== 'php')
				{
					continue;
				}

				$files[] = str_replace($root . '/', '', $file->getPathname());
			}
		}

		$files = array_merge($files, self::EXTRA_FILES, ['index.php']);

		sort($files);

		return $files;
	}

	/**
	 * The PHP files under user_code/ which are tracked in Git.
	 *
	 * The folder is where users put their own code, so a developer's working copy holds untracked, unguarded files such
	 * as `early_bootstrap.php`. Only the tracked files — the sample template overrides — are ours to check.
	 *
	 * @return  array<string>|null  NULL when Git is not available.
	 */
	private static function trackedUserCodeFiles(): ?array
	{
		$root = self::root();

		if (!is_dir($root . '/.git') && !is_file($root . '/.git'))
		{
			return null;
		}

		$output = [];
		$result = 1;

		@exec('git -C ' . escapeshellarg($root) . ' ls-files -- user_code 2>/dev/null', $output, $result);

		if ($result !== 0)
		{
			return null;
		}

		return array_values(array_filter($output, fn(string $file) => str_ends_with($file, '.php')));
	}

	/**
	 * @param   array<string>  $files  Repository-relative paths.
	 *
	 * @return  array<string>  The files which are neither guarded nor exempt.
	 */
	private static function unguarded(array $files): array
	{
		$offenders = [];

		foreach ($files as $file)
		{
			if (array_key_exists($file, self::EXEMPT))
			{
				continue;
			}

			if (!self::hasGuard((string) file_get_contents(self::root() . '/' . $file)))
			{
				$offenders[] = $file;
			}
		}

		return $offenders;
	}

	/**
	 * Guard against the scan silently becoming a no-op.
	 */
	public function testTheScanActuallyFindsFiles(): void
	{
		$files = self::shippedFiles();

		$this->assertGreaterThan(500, count($files), 'The scan found implausibly few files');
		$this->assertContains('src/Container.php', $files);
		$this->assertContains('ViewTemplates/Sites/default.blade.php', $files);
		$this->assertContains('includes/bootstrap.php', $files);
		$this->assertContains('cli/panopticon.php', $files);

		foreach (self::EXTRA_FILES as $file)
		{
			$this->assertFileExists(self::root() . '/' . $file);
		}
	}

	/**
	 * The detector has to be known-good, otherwise the scan is worthless.
	 *
	 * @param   string  $code      The source to examine.
	 * @param   bool    $expected  Whether it counts as guarded.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('detectorProvider')]
	public function testTheDetectorWorks(string $code, bool $expected): void
	{
		$this->assertSame($expected, self::hasGuard($code));
	}

	public static function detectorProvider(): array
	{
		return [
			'class file'              => ["<?php\nnamespace Foo;\n\ndefined('AKEEBA') || die;\n\nclass Bar {}", true],
			'same line as open tag'   => ["<?php defined('AKEEBA') || die;\nclass AConfig {}", true],
			'double quotes'           => ["<?php\ndefined(\"AKEEBA\") || die;", true],
			'or die()'                => ["<?php\ndefined('AKEEBA') or die();", true],
			'template'                => ["<?php\nuse Foo;\n\ndefined('AKEEBA') || die;\n?>\n<div></div>", true],
			'missing'                 => ["<?php\nnamespace Foo;\n\nclass Bar {}", false],
			'after the first block'   => ["<?php\n\$a = 1;\n?>\n<p></p>\n<?php defined('AKEEBA') || die; ?>", false],
			'commented out'           => ["<?php\n// defined('AKEEBA') || die;\nclass Bar {}", false],
			'other constant'          => ["<?php\ndefined('_JEXEC') || die;", false],
			'output before open tag'  => ["Hello\n<?php\ndefined('AKEEBA') || die;", false],
		];
	}

	/**
	 * Every shipped PHP file has the guard line, unless it is explicitly exempt.
	 */
	public function testShippedFilesHaveTheGuardLine(): void
	{
		$offenders = self::unguarded(self::shippedFiles());

		$this->assertSame(
			[],
			$offenders,
			sprintf(
				"These files lack the `defined('AKEEBA') || die;` guard line in their first PHP block, so they run "
				. "out of context when requested directly over the web:\n  %s",
				implode("\n  ", $offenders)
			)
		);
	}

	/**
	 * The sample template overrides in user_code/ are copied by users into their own installations, guard and all.
	 */
	public function testTrackedUserCodeFilesHaveTheGuardLine(): void
	{
		$files = self::trackedUserCodeFiles();

		if ($files === null)
		{
			$this->markTestSkipped('Git is not available; cannot tell tracked user_code files from the user\'s own.');
		}

		$this->assertNotEmpty($files, 'Found no tracked PHP files under user_code/');

		$offenders = self::unguarded($files);

		$this->assertSame(
			[],
			$offenders,
			sprintf(
				"These tracked user_code files lack the `defined('AKEEBA') || die;` guard line:\n  %s",
				implode("\n  ", $offenders)
			)
		);
	}

	/**
	 * Every exemption must still point to a real file which really lacks the guard. A stale entry silently widens the
	 * hole the moment someone creates a new file at that path.
	 */
	public function testExemptionsAreNotStale(): void
	{
		foreach (array_keys(self::EXEMPT) as $file)
		{
			$path = self::root() . '/' . $file;

			$this->assertFileExists($path, sprintf('Exempt file %s no longer exists', $file));
			$this->assertFalse(
				self::hasGuard((string) file_get_contents($path)),
				sprintf('Exempt file %s now has the guard line; remove its exemption', $file)
			);
		}
	}

	/**
	 * Folders which are never served directly carry the deny-all files for Apache and IIS, identical to those in src/.
	 */
	public function testNonPublicFoldersHaveDenyFiles(): void
	{
		$root     = self::root();
		$missing  = [];
		$expected = [
			'.htaccess'  => (string) file_get_contents($root . '/src/.htaccess'),
			'web.config' => (string) file_get_contents($root . '/src/web.config'),
		];

		$this->assertStringContainsString('Require all denied', $expected['.htaccess']);
		$this->assertStringContainsString('<deny users ="*" />', $expected['web.config']);

		foreach (self::DENIED_DIRECTORIES as $directory)
		{
			foreach ($expected as $fileName => $contents)
			{
				$path = $root . '/' . $directory . '/' . $fileName;

				if (!is_file($path) || file_get_contents($path) !== $contents)
				{
					$missing[] = $directory . '/' . $fileName;
				}
			}
		}

		$this->assertSame(
			[],
			$missing,
			"These deny-all files are missing, or differ from src/.htaccess and src/web.config:\n  "
			. implode("\n  ", $missing)
		);
	}
}
