<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\SiteInfo;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\IntegrityState;
use Akeeba\Panopticon\Library\SiteInfo\CoreChecksumsState;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use Awf\Registry\Registry;

/**
 * Tests for the core file integrity state helper (gh-1079).
 *
 * @since 2.5.0
 */
class CoreChecksumsStateTest extends AbstractUnitTestCase
{
	private function make(array $data, CMSType $cms = CMSType::JOOMLA): ?array
	{
		return CoreChecksumsState::fromConfig(new Registry(['core' => ['coreChecksums' => $data]]), $cms);
	}

	public function testNotApplicableToUnknownCms(): void
	{
		$this->assertNull($this->make(['lastCheck' => 1000, 'lastStatus' => true], CMSType::UNKNOWN));
	}

	public function testWordPressIsApplicable(): void
	{
		$r = $this->make(['lastCheck' => 1000, 'lastStatus' => true], CMSType::WORDPRESS);

		$this->assertNotNull($r);
		$this->assertSame(IntegrityState::Ok, $r['state']);
	}

	public function testNeverRun(): void
	{
		$r = $this->make([]);

		$this->assertSame(IntegrityState::NeverRun, $r['state']);
		$this->assertNull($r['lastCheck']);
		$this->assertNull($r['lastFailure']);
		$this->assertFalse($r['latestAttemptFailed']);
		$this->assertSame(0, $r['modifiedCount']);
	}

	public function testNeverRunWithEmptyConfig(): void
	{
		$r = CoreChecksumsState::fromConfig(new Registry(), CMSType::JOOMLA);

		$this->assertSame(IntegrityState::NeverRun, $r['state']);
	}

	public function testOk(): void
	{
		$r = $this->make(['lastCheck' => 1000, 'lastStatus' => true, 'modifiedCount' => 0]);

		$this->assertSame(IntegrityState::Ok, $r['state']);
		$this->assertFalse($r['latestAttemptFailed']);
	}

	public function testOkWithOlderFailure(): void
	{
		$r = $this->make(['lastCheck' => 2000, 'lastStatus' => true, 'lastFailure' => 1000]);

		$this->assertSame(IntegrityState::Ok, $r['state']);
		$this->assertFalse($r['latestAttemptFailed']);
		$this->assertSame(1000, $r['lastFailure']);
	}

	public function testNullStatusCountsAsOk(): void
	{
		$r = $this->make(['lastCheck' => 1000, 'lastStatus' => null]);

		$this->assertSame(IntegrityState::Ok, $r['state']);
	}

	public function testProblems(): void
	{
		$r = $this->make(['lastCheck' => 1000, 'lastStatus' => false, 'modifiedCount' => 3]);

		$this->assertSame(IntegrityState::Problems, $r['state']);
		$this->assertFalse($r['latestAttemptFailed']);
		$this->assertSame(3, $r['modifiedCount']);
	}

	public function testProblemsWinOverNewerFailure(): void
	{
		$r = $this->make([
			'lastCheck' => 1000, 'lastStatus' => false, 'modifiedCount' => 2,
			'lastFailure' => 2000, 'lastFailureReason' => 'Timeout',
		]);

		$this->assertSame(IntegrityState::Problems, $r['state']);
		$this->assertTrue($r['latestAttemptFailed']);
		$this->assertSame('Timeout', $r['failureReason']);
	}

	public function testFailedWithNoPriorCheck(): void
	{
		$r = $this->make(['lastFailure' => 2000, 'lastFailureReason' => 'Nope']);

		$this->assertSame(IntegrityState::Failed, $r['state']);
		$this->assertTrue($r['latestAttemptFailed']);
		$this->assertNull($r['lastCheck']);
		$this->assertSame('Nope', $r['failureReason']);
	}

	public function testFailedAfterCleanCheck(): void
	{
		$r = $this->make(['lastCheck' => 1000, 'lastStatus' => true, 'lastFailure' => 2000]);

		$this->assertSame(IntegrityState::Failed, $r['state']);
		$this->assertTrue($r['latestAttemptFailed']);
		$this->assertNull($r['failureReason']);
	}

	public function testZeroOrNullFailureMeansNoFailure(): void
	{
		$this->assertSame(IntegrityState::NeverRun, $this->make(['lastFailure' => 0])['state']);
		$this->assertSame(IntegrityState::Ok, $this->make(['lastCheck' => 5, 'lastStatus' => true, 'lastFailure' => null])['state']);
	}

	public function testValuesAreCastAndPassedThrough(): void
	{
		$r = $this->make(['lastCheck' => '1234', 'lastStatus' => true, 'modifiedCount' => '7', 'lastFailure' => '1000']);

		$this->assertSame(1234, $r['lastCheck']);
		$this->assertSame(7, $r['modifiedCount']);
		$this->assertSame(1000, $r['lastFailure']);
	}
}
