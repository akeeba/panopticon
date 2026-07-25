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
 * Asserts that {@see \Akeeba\Panopticon\Library\Http\HttpFactory} is the only place which constructs a Guzzle client.
 *
 * The forbidden IP ranges guard which remediates GHSA-6234-p3mh-7j3x is a Guzzle middleware pushed onto the stack by
 * the factory. Every HTTP call site in the application inherits it for free — but only for as long as the factory
 * remains the sole way to obtain a client. A client built with `new Client()` somewhere else carries no guard, and
 * nothing about the resulting code looks wrong at a glance.
 *
 * The scope is deliberately narrow. This checks *one* mechanism — Guzzle client construction — for which there is
 * exactly one correct answer and therefore no legitimate exception beyond the factory itself. It is explicitly not a
 * check for "all outbound connections are guarded": raw sockets have legitimate uses which cannot go through the
 * factory at all (see {@see \Akeeba\Panopticon\Model\Site::getCertificateInformation()}, which needs the peer
 * certificate and therefore a raw TLS socket, and repeats the check by hand). A scan broad enough to cover those would
 * need an allowlist, and an allowlist entry is precisely what an author adds the moment the check inconveniences them.
 *
 * @since 1.4.0
 */
class NoDirectGuzzleClientTest extends AbstractUnitTestCase
{
	/**
	 * The one file allowed to construct a Guzzle client, relative to the repository root.
	 */
	private const FACTORY = 'src/Library/Http/HttpFactory.php';

	/**
	 * Find the names under which a file can construct a Guzzle client, and report whether it does.
	 *
	 * Both the imported form (`use GuzzleHttp\Client;` … `new Client()`), the aliased form, and the fully qualified
	 * form are recognised. Keying the unqualified form on the import is what keeps this from firing on some unrelated
	 * class which merely happens to be called `Client`.
	 *
	 * @param   string  $code  The PHP source to examine.
	 *
	 * @return  bool  TRUE if the source constructs a Guzzle client.
	 */
	private static function constructsGuzzleClient(string $code): bool
	{
		// Fully qualified: new \GuzzleHttp\Client() or new GuzzleHttp\Client()
		if (preg_match('/\bnew\s+\\\\?GuzzleHttp\\\\Client\s*\(/', $code))
		{
			return true;
		}

		// Imported, optionally aliased: use GuzzleHttp\Client [as Something];
		if (!preg_match_all('/^\s*use\s+GuzzleHttp\\\\Client(?:\s+as\s+(\w+))?\s*;/mi', $code, $matches, PREG_SET_ORDER))
		{
			return false;
		}

		foreach ($matches as $match)
		{
			$localName = ($match[1] ?? '') ?: 'Client';

			if (preg_match('/\bnew\s+' . preg_quote($localName, '/') . '\s*\(/', $code))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return  array<string>  Every PHP file under src/, as a repository-relative path.
	 */
	private static function sourceFiles(): array
	{
		$root     = realpath(__DIR__ . '/../../..');
		$srcPath  = $root . '/src';
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS)
		);

		$files = [];

		/** @var SplFileInfo $file */
		foreach ($iterator as $file)
		{
			if (!$file->isFile() || $file->getExtension() !== 'php')
			{
				continue;
			}

			$files[] = str_replace($root . '/', '', $file->getPathname());
		}

		sort($files);

		return $files;
	}

	/**
	 * Guard against the scan silently becoming a no-op — a wrong path or a broken iterator would otherwise make this
	 * whole test file pass by examining nothing at all.
	 */
	public function testTheScanActuallyFindsFiles(): void
	{
		$files = self::sourceFiles();

		$this->assertGreaterThan(100, count($files), 'The source scan found implausibly few files');
		$this->assertContains(self::FACTORY, $files, 'The source scan did not find the HTTP factory itself');
	}

	/**
	 * The detector has to be known-good, otherwise the scan below is worthless: a detector which never matches anything
	 * produces a permanently green test.
	 *
	 * @param   string  $code      The source snippet to examine.
	 * @param   bool    $expected  Whether the snippet should be flagged.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('detectorProvider')]
	public function testTheDetectorWorks(string $code, bool $expected): void
	{
		$this->assertSame($expected, self::constructsGuzzleClient($code));
	}

	public static function detectorProvider(): array
	{
		return [
			'imported'            => ["use GuzzleHttp\\Client;\n\$c = new Client();", true],
			'imported, spaced'    => ["use GuzzleHttp\\Client;\n\$c = new  Client (\$options);", true],
			'aliased'             => ["use GuzzleHttp\\Client as Guzzler;\n\$c = new Guzzler();", true],
			'fully qualified'     => ['$c = new \\GuzzleHttp\\Client();', true],
			'qualified, no slash' => ['$c = new GuzzleHttp\\Client();', true],
			'imported, unused'    => ["use GuzzleHttp\\Client;\n\$c = \$factory->makeClient();", false],
			'unrelated Client'    => ["use Symfony\\Component\\Foo\\Client;\n\$c = new Client();", false],
			'no import at all'    => ['$c = new Client();', false],
			'type hint only'      => ["use GuzzleHttp\\Client;\nfunction f(Client \$c) {}", false],
			'factory call'        => ['$c = $this->container->httpFactory->makeClient();', false],
		];
	}

	/**
	 * The check itself.
	 */
	public function testOnlyTheFactoryConstructsAGuzzleClient(): void
	{
		$offenders = [];

		foreach (self::sourceFiles() as $file)
		{
			if ($file === self::FACTORY)
			{
				continue;
			}

			if (self::constructsGuzzleClient((string) file_get_contents(dirname(__DIR__, 3) . '/' . $file)))
			{
				$offenders[] = $file;
			}
		}

		$this->assertSame(
			[],
			$offenders,
			sprintf(
				"These files construct a Guzzle client directly, so the client has no forbidden IP ranges guard on it "
				. "and GHSA-6234-p3mh-7j3x is reopened for every request it makes:\n  %s\n\n"
				. "Use %s::makeClient() instead. If you need a custom handler stack, pass it in the \$stack parameter "
				. "— the guard is pushed onto it.",
				implode("\n  ", $offenders),
				'Akeeba\\Panopticon\\Library\\Http\\HttpFactory'
			)
		);
	}

	/**
	 * The factory is exempt because it is the thing which installs the guard. If it ever stops constructing a client
	 * the exemption is stale, and more importantly the assumption behind this entire test file has changed.
	 */
	public function testTheFactoryStillConstructsTheClientItIsExemptedFor(): void
	{
		$this->assertTrue(
			self::constructsGuzzleClient((string) file_get_contents(dirname(__DIR__, 3) . '/' . self::FACTORY)),
			'The HTTP factory no longer constructs a Guzzle client. Its exemption from this check is now stale.'
		);
	}
}
