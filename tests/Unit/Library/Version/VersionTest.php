<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Version;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Unit tests for Version parsing, stability detection, and tag normalisation.
 *
 * @since  2.2.0
 */
class VersionTest extends AbstractUnitTestCase
{
	// -------------------------------------------------------------------------
	// Numeric parts and version family
	// -------------------------------------------------------------------------

	public function testThreePartVersionParsesAllParts(): void
	{
		$v = Version::create('4.4.2');

		$this->assertSame(4, $v->major());
		$this->assertSame(4, $v->minor());
		$this->assertSame(2, $v->patch());
	}

	public function testThreePartVersionFamily(): void
	{
		$v = Version::create('4.4.2');

		$this->assertSame('4.4', $v->versionFamily());
	}

	public function testTwoPartVersionPadsPatchToZero(): void
	{
		$v = Version::create('4.4');

		$this->assertSame(4, $v->major());
		$this->assertSame(4, $v->minor());
		$this->assertSame(0, $v->patch());
		$this->assertSame('4.4', $v->versionFamily());
	}

	public function testOnePartVersionPadsMinorAndPatchToZero(): void
	{
		$v = Version::create('5');

		$this->assertSame(5, $v->major());
		$this->assertSame(0, $v->minor());
		$this->assertSame(0, $v->patch());
		$this->assertSame('5.0', $v->versionFamily());
	}

	public function testSixZeroZeroVersion(): void
	{
		$v = Version::create('6.0.0');

		$this->assertSame(6, $v->major());
		$this->assertSame(0, $v->minor());
		$this->assertSame(0, $v->patch());
	}

	// -------------------------------------------------------------------------
	// shortVersion()
	// -------------------------------------------------------------------------

	public function testShortVersionWithNonZeroPatch(): void
	{
		$this->assertSame('4.4.2', Version::create('4.4.2')->shortVersion());
	}

	public function testShortVersionDropsPatchWhenZero(): void
	{
		$this->assertSame('4.4', Version::create('4.4.0')->shortVersion());
	}

	public function testShortVersionDropsMinorAndPatchWhenBothZero(): void
	{
		$this->assertSame('4', Version::create('4.0.0')->shortVersion());
	}

	public function testShortVersionForceThreePartsOverridesDropping(): void
	{
		$this->assertSame('4.0.0', Version::create('4.0.0')->shortVersion(true));
	}

	// -------------------------------------------------------------------------
	// Beta tag
	// -------------------------------------------------------------------------

	public function testBetaVersionIsBeta(): void
	{
		$v = Version::create('5.0.0-beta2');

		$this->assertTrue($v->isBeta());
	}

	public function testBetaVersionIsNotStable(): void
	{
		$v = Version::create('5.0.0-beta2');

		$this->assertFalse($v->isStable());
	}

	public function testBetaVersionTagType(): void
	{
		$v = Version::create('5.0.0-beta2');

		$this->assertSame(Version::TAG_TYPE_BETA, $v->tagType());
	}

	public function testBetaVersionTagNumber(): void
	{
		$v = Version::create('5.0.0-beta2');

		$this->assertSame(2, $v->tagNumber());
	}

	public function testBetaVersionIsTesting(): void
	{
		$v = Version::create('5.0.0-beta2');

		$this->assertTrue($v->isTesting());
	}

	// -------------------------------------------------------------------------
	// Alpha tag
	// -------------------------------------------------------------------------

	public function testAlphaVersionIsAlpha(): void
	{
		$v = Version::create('5.0.0-alpha');

		$this->assertTrue($v->isAlpha());
	}

	public function testAlphaVersionWithoutNumberDefaultsTagNumberToOne(): void
	{
		// parseTag() sets tagNumber to 1 when it remains 0 after iterating all parts
		$v = Version::create('5.0.0-alpha');

		$this->assertSame(1, $v->tagNumber());
	}

	// -------------------------------------------------------------------------
	// RC tag
	// -------------------------------------------------------------------------

	public function testRcVersionIsRC(): void
	{
		$v = Version::create('5.0.0-rc1');

		$this->assertTrue($v->isRC());
	}

	public function testRcVersionTagNumberOne(): void
	{
		$v = Version::create('5.0.0-rc1');

		$this->assertSame(1, $v->tagNumber());
	}

	// -------------------------------------------------------------------------
	// Dev tag
	// -------------------------------------------------------------------------

	public function testDevVersionIsDev(): void
	{
		$v = Version::create('5.0.0-dev');

		$this->assertTrue($v->isDev());
	}

	// -------------------------------------------------------------------------
	// Stable (no tag)
	// -------------------------------------------------------------------------

	public function testStableVersionIsStable(): void
	{
		$v = Version::create('5.0.0');

		$this->assertTrue($v->isStable());
	}

	public function testStableVersionIsNotTesting(): void
	{
		$v = Version::create('5.0.0');

		$this->assertFalse($v->isTesting());
	}

	public function testStableVersionTagTypeIsNone(): void
	{
		$v = Version::create('5.0.0');

		$this->assertSame(Version::TAG_TYPE_NONE, $v->tagType());
	}

	public function testStableVersionTagNumberIsZero(): void
	{
		$v = Version::create('5.0.0');

		$this->assertSame(0, $v->tagNumber());
	}

	public function testStableVersionHasNoTag(): void
	{
		$v = Version::create('5.0.0');

		$this->assertFalse($v->hasTag());
	}

	// -------------------------------------------------------------------------
	// Underscore normalisation (treated as hyphen separator)
	// -------------------------------------------------------------------------

	public function testUnderscoreSeparatorNormalisedToBeta(): void
	{
		$v = Version::create('5.0.0_beta3');

		$this->assertTrue($v->isBeta());
	}

	public function testUnderscoreSeparatorTagNumber(): void
	{
		$v = Version::create('5.0.0_beta3');

		$this->assertSame(3, $v->tagNumber());
	}

	// -------------------------------------------------------------------------
	// Branch name
	// -------------------------------------------------------------------------

	public function testBranchNameDetected(): void
	{
		$v = Version::create('1.2.3-mybranch');

		$this->assertTrue($v->hasBranch());
	}

	public function testBranchNameValue(): void
	{
		$v = Version::create('1.2.3-mybranch');

		$this->assertSame('mybranch', $v->branchName());
	}

	public function testBranchOnlyVersionIsStable(): void
	{
		// A non-recognised tag keyword is treated as a branch, not a stability tag.
		// tagType stays NONE, so isStable() is true.
		$v = Version::create('1.2.3-mybranch');

		$this->assertTrue($v->isStable());
	}

	public function testBranchOnlyVersionTagTypeIsNone(): void
	{
		$v = Version::create('1.2.3-mybranch');

		$this->assertSame(Version::TAG_TYPE_NONE, $v->tagType());
	}

	// -------------------------------------------------------------------------
	// fullVersion()
	// -------------------------------------------------------------------------

	public function testFullVersionStableReturnsVersionUnchanged(): void
	{
		$v = Version::create('5.0.0');

		$this->assertSame('5.0.0', $v->fullVersion());
	}

	public function testFullVersionBetaContainsBetaKeyword(): void
	{
		// fullVersion() appends '-' + normalised tag to the raw input string.
		// We assert the result contains 'beta' without over-specifying exact format.
		$v = Version::create('5.0.0-beta2');

		$this->assertStringContainsString('beta', $v->fullVersion());
	}
}
