<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Drift detector for the vendored, locally modified Symfony error-handler classes in patches/symfony-error-handler/.
 *
 * BootstrapUtilities::overrideHtmlErrorRenderer() loads those hand-maintained copies in place of the upstream classes.
 * They are pinned to a specific symfony/error-handler release (see the hashes below). When Composer pulls a newer
 * release whose source differs, this test fails so the copies get re-synced instead of silently drifting out of date.
 *
 * To re-sync when this test fails, run: phing fix-error-handling (see patches/README.md for details).
 *
 * @since  2.2.1
 */
class ErrorHandlerPatchesTest extends AbstractUnitTestCase
{
	/**
	 * SHA-256 of each pinned upstream file (symfony/error-handler v6.4.43).
	 *
	 * @var  array<string, string>
	 */
	private const UPSTREAM_HASHES = [
		'/vendor/symfony/error-handler/Exception/FlattenException.php'         => '3e5f2ac08cd172492d7aaac6e932683eb1ca02a30c8000781175e6bd25e670ca',
		'/vendor/symfony/error-handler/ErrorRenderer/HtmlErrorRenderer.php'    => '4cd94c12f2385de11c691a45739fa1383c3b12e30d5120a35c02b55e80507d81',
	];

	/**
	 * Our locally modified copies that stand in for the upstream classes.
	 *
	 * @var  string[]
	 */
	private const LOCAL_COPIES = [
		'/patches/symfony-error-handler/FlattenException.php',
		'/patches/symfony-error-handler/HtmlErrorRenderer.php',
	];

	public static function upstreamHashProvider(): array
	{
		$cases = [];

		foreach (self::UPSTREAM_HASHES as $relativePath => $expectedHash)
		{
			$cases[$relativePath] = [$relativePath, $expectedHash];
		}

		return $cases;
	}

	public static function localCopyProvider(): array
	{
		$cases = [];

		foreach (self::LOCAL_COPIES as $relativePath)
		{
			$cases[$relativePath] = [$relativePath];
		}

		return $cases;
	}

	/**
	 * @dataProvider upstreamHashProvider
	 */
	public function testUpstreamSourceHasNotDrifted(string $relativePath, string $expectedHash): void
	{
		$path = APATH_ROOT . $relativePath;

		if (!is_file($path))
		{
			$this->markTestSkipped(
				sprintf('Upstream file %s is not installed; cannot verify drift.', $relativePath)
			);
		}

		$this->assertSame(
			$expectedHash,
			hash_file('sha256', $path),
			sprintf(
				"Upstream %s has changed. Our locally modified copy in patches/symfony-error-handler/ may now be "
				. "out of date.\nRun: phing fix-error-handling\nThat re-syncs the copy and updates the expected "
				. "hash in %s for you. See patches/README.md for what it does under the hood.",
				$relativePath,
				basename(__FILE__)
			)
		);
	}

	/**
	 * @dataProvider localCopyProvider
	 */
	public function testLocalCopyExists(string $relativePath): void
	{
		$this->assertFileExists(
			APATH_ROOT . $relativePath,
			sprintf('The vendored copy %s must be present; BootstrapUtilities requires it at bootstrap.', $relativePath)
		);
	}

	/**
	 * @dataProvider localCopyProvider
	 */
	public function testLocalCopyKeepsCustomisationMarkers(string $relativePath): void
	{
		$contents = file_get_contents(APATH_ROOT . $relativePath);

		$this->assertStringContainsString(
			'AKEEBA PANOPTICON CUSTOMISATION',
			$contents,
			sprintf(
				'The vendored copy %s lost its customisation marker(s). It may have been overwritten with a pristine '
				. 'upstream copy, dropping our modifications.',
				$relativePath
			)
		);
	}

	/**
	 * @dataProvider localCopyProvider
	 */
	public function testLocalCopyIsValidPhp(string $relativePath): void
	{
		$path   = APATH_ROOT . $relativePath;
		$output = [];
		$status = 1;

		exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($path)), $output, $status);

		$this->assertSame(
			0,
			$status,
			sprintf("The vendored copy %s is not valid PHP:\n%s", $relativePath, implode("\n", $output))
		);
	}
}
