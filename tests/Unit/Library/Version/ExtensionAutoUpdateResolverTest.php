<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Version;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\ExtensionAutoUpdateResolver;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Unit tests for the pure ExtensionAutoUpdateResolver decision helper.
 *
 * @since 1.1.0
 */
class ExtensionAutoUpdateResolverTest extends AbstractUnitTestCase
{
	// -------------------------------------------------------------------------
	// Guard: not an upgrade => always false
	// -------------------------------------------------------------------------

	public function testReturnsFalseWhenNewVersionIsOlder(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '2.0.0', '1.9.0')
		);
	}

	public function testReturnsFalseWhenNewVersionIsOlderWithNonePreference(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('none', '2.0.0', '1.9.0')
		);
	}

	public function testReturnsFalseWhenVersionsAreEqual(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '1.2.3', '1.2.3')
		);
	}

	// -------------------------------------------------------------------------
	// Guard: empty version strings => always false
	// -------------------------------------------------------------------------

	public function testReturnsFalseWhenOldVersionIsEmpty(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '', '2.0.0')
		);
	}

	public function testReturnsFalseWhenNewVersionIsEmpty(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '1.0.0', '')
		);
	}

	public function testReturnsFalseWhenBothVersionsAreEmpty(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '', '')
		);
	}

	// -------------------------------------------------------------------------
	// Preference: major
	// -------------------------------------------------------------------------

	public function testReturnsTrueForMajorUpgradeWithMajorPreference(): void
	{
		$this->assertTrue(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '1.0.0', '2.0.0')
		);
	}

	public function testReturnsTrueForMinorUpgradeWithMajorPreference(): void
	{
		$this->assertTrue(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '1.2.0', '1.9.0')
		);
	}

	public function testReturnsTrueForPatchUpgradeWithMajorPreference(): void
	{
		$this->assertTrue(
			ExtensionAutoUpdateResolver::willAutoUpdate('major', '1.2.3', '1.2.9')
		);
	}

	// -------------------------------------------------------------------------
	// Preference: minor
	// -------------------------------------------------------------------------

	public function testReturnsTrueForMinorWhenSameMajor(): void
	{
		$this->assertTrue(
			ExtensionAutoUpdateResolver::willAutoUpdate('minor', '1.2.0', '1.9.0')
		);
	}

	public function testReturnsFalseForMinorWhenDifferentMajor(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('minor', '1.9.0', '2.0.0')
		);
	}

	// -------------------------------------------------------------------------
	// Preference: patch
	// -------------------------------------------------------------------------

	public function testReturnsTrueForPatchWhenSameFamily(): void
	{
		$this->assertTrue(
			ExtensionAutoUpdateResolver::willAutoUpdate('patch', '1.2.3', '1.2.9')
		);
	}

	public function testReturnsFalseForPatchWhenDifferentMinor(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('patch', '1.2.0', '1.3.0')
		);
	}

	public function testReturnsFalseForPatchWhenDifferentMajor(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('patch', '1.9.0', '2.0.0')
		);
	}

	// -------------------------------------------------------------------------
	// Unknown / none / empty preference => always false (even for a real upgrade)
	// -------------------------------------------------------------------------

	public function testReturnsFalseForNonePreference(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('none', '1.0.0', '2.0.0')
		);
	}

	public function testReturnsFalseForEmptyPreference(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('', '1.0.0', '2.0.0')
		);
	}

	public function testReturnsFalseForUnknownPreference(): void
	{
		$this->assertFalse(
			ExtensionAutoUpdateResolver::willAutoUpdate('always', '1.0.0', '2.0.0')
		);
	}
}
