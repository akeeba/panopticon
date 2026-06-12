<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\SoftwareVersions;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\SoftwareVersions\JoomlaVersion;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use DateTime;
use GuzzleHttp\ClientInterface;

/**
 * Unit tests for JoomlaVersion::getVersionInformation() boundary logic and isSecurity().
 *
 * The Joomla EOL data is hardcoded; passing a stub HTTP client prevents the constructor from
 * calling the HTTP/date factories, keeping these tests fully deterministic and network-free.
 *
 * @since 1.4.0
 */
class JoomlaVersionTest extends AbstractUnitTestCase
{
	private JoomlaVersion $joomlaVersion;

	protected function setUp(): void
	{
		parent::setUp();

		/** @var ClientInterface $stubClient */
		$stubClient          = $this->createStub(ClientInterface::class);
		$this->joomlaVersion = new JoomlaVersion(Factory::getContainer(), $stubClient);
	}

	// -------------------------------------------------------------------------
	// getVersionInformation — 4.4 family boundary tests
	// -------------------------------------------------------------------------

	/**
	 * 2024-01-01 is before activeSupport (2024-10-17) → supported, not security, not eolBranch, not eol, not unknown.
	 */
	public function testGetVersionInformation44SupportedBeforeActiveSupport(): void
	{
		$today  = new DateTime('2024-01-01');
		$result = $this->joomlaVersion->getVersionInformation('4.4.0', $today);

		$this->assertFalse($result->unknown, 'unknown must be false for a known version family');
		$this->assertSame('4.x', $result->series, 'series must be 4.x for 4.4 family');
		$this->assertTrue($result->supported, 'supported must be true before activeSupport date');
		$this->assertFalse($result->security, 'security must be false when supported is true');
		$this->assertFalse($result->eolBranch, 'eolBranch must be false before eolBranch date');
		$this->assertFalse($result->eol, 'eol must be false before eolMajor date');
	}

	/**
	 * 2025-01-01 is after activeSupport (2024-10-17) but before eolBranch (2025-10-17)
	 * → security-only, not supported, not eolBranch, not eol.
	 */
	public function testGetVersionInformation44SecurityBetweenActiveSupportAndEolBranch(): void
	{
		$today  = new DateTime('2025-01-01');
		$result = $this->joomlaVersion->getVersionInformation('4.4.0', $today);

		$this->assertFalse($result->unknown, 'unknown must be false for a known version family');
		$this->assertSame('4.x', $result->series);
		$this->assertFalse($result->supported, 'supported must be false after activeSupport date');
		$this->assertTrue($result->security, 'security must be true in the security-only window');
		$this->assertFalse($result->eolBranch, 'eolBranch must be false before eolBranch date');
		$this->assertFalse($result->eol, 'eol must be false before eolMajor date');
	}

	/**
	 * 2026-01-01 is after both eolBranch (2025-10-17) and eolMajor (2025-10-17)
	 * → eol true, eolBranch true, supported false, security false.
	 */
	public function testGetVersionInformation44EolAfterBothEolDates(): void
	{
		$today  = new DateTime('2026-01-01');
		$result = $this->joomlaVersion->getVersionInformation('4.4.0', $today);

		$this->assertFalse($result->unknown, 'unknown must be false for a known version family');
		$this->assertSame('4.x', $result->series);
		$this->assertTrue($result->eol, 'eol must be true after eolMajor date');
		$this->assertTrue($result->eolBranch, 'eolBranch must be true after eolBranch date');
		$this->assertFalse($result->supported, 'supported must be false when eol is true');
		$this->assertFalse($result->security, 'security must be false when eolBranch/eol is true');
	}

	/**
	 * An unmapped version family (99.9.x) must trigger the early-return path:
	 * unknown true, series null, all flags false.
	 */
	public function testGetVersionInformationUnknownFamilyReturnsUnknown(): void
	{
		$today  = new DateTime('2025-01-01');
		$result = $this->joomlaVersion->getVersionInformation('99.9.9', $today);

		$this->assertTrue($result->unknown, 'unknown must be true for an unmapped version family');
		$this->assertNull($result->series, 'series must be null for an unmapped version family');
		$this->assertFalse($result->supported, 'supported must be false for unknown version');
		$this->assertFalse($result->security, 'security must be false for unknown version');
		$this->assertFalse($result->eolBranch, 'eolBranch must be false for unknown version');
		$this->assertFalse($result->eol, 'eol must be false for unknown version');
	}

	// -------------------------------------------------------------------------
	// Date objects on the returned result
	// -------------------------------------------------------------------------

	/**
	 * The dates object must be populated for a known family.
	 */
	public function testGetVersionInformationPopulatesDates(): void
	{
		$today  = new DateTime('2024-01-01');
		$result = $this->joomlaVersion->getVersionInformation('4.4.0', $today);

		$this->assertInstanceOf(DateTime::class, $result->dates->firstRelease);
		$this->assertInstanceOf(DateTime::class, $result->dates->activeSupport);
		$this->assertInstanceOf(DateTime::class, $result->dates->eolBranch);
		$this->assertInstanceOf(DateTime::class, $result->dates->eol);

		// Verify the hardcoded 4.4 dates match what the source declares
		$this->assertSame('2023-10-17', $result->dates->firstRelease->format('Y-m-d'));
		$this->assertSame('2024-10-17', $result->dates->activeSupport->format('Y-m-d'));
		$this->assertSame('2025-10-17', $result->dates->eolBranch->format('Y-m-d'));
		$this->assertSame('2025-10-17', $result->dates->eol->format('Y-m-d'));
	}

	// -------------------------------------------------------------------------
	// isSecurity() — verify the fix (positive sense, consistent with isEOLBranch/isEOLMajor)
	// -------------------------------------------------------------------------

	/**
	 * isSecurity() must now return TRUE when the version is in the security-only window as
	 * determined by getVersionInformation(). We confirm via getVersionInformation() with an
	 * injected $today (positive-sense sanity check independent of the real clock).
	 *
	 * The 4.4 branch: activeSupport 2024-10-17, eolBranch 2025-10-17.
	 * At 2025-01-01 the security flag is true; the previous (buggy) implementation would have
	 * returned false from isSecurity() because it negated the flag.
	 */
	public function testSecurityFlagHasPositiveSenseViaGetVersionInformation(): void
	{
		$today  = new DateTime('2025-01-01');
		$result = $this->joomlaVersion->getVersionInformation('4.4.0', $today);

		// The underlying flag must be positive (true = is in security-only window)
		$this->assertTrue(
			$result->security,
			'security flag must be TRUE in the security-only window — positive sense required'
		);
	}

	/**
	 * isSecurity() on a long-dead version (Joomla 1.0, EOL 2009) must return false —
	 * a version that is fully EOL is not "security-only".
	 * This also validates the method is callable and uses the real current date path safely.
	 */
	public function testIsSecurityReturnsFalseForFullyEolVersion(): void
	{
		// Joomla 1.0 EOL'd in 2009 — always EOL regardless of current date
		$this->assertFalse(
			$this->joomlaVersion->isSecurity('1.0.0'),
			'isSecurity() must return false for a fully EOL version'
		);
	}

	/**
	 * isSecurity() on an unknown family must return false.
	 */
	public function testIsSecurityReturnsFalseForUnknownFamily(): void
	{
		$this->assertFalse(
			$this->joomlaVersion->isSecurity('99.9.9'),
			'isSecurity() must return false for an unknown version family'
		);
	}
}
