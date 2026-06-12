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
use RuntimeException;

/**
 * Tests for the checksums_base_url config-key validator in DefaultConfigurationTrait.
 *
 * The trait's validateOptionalUrl() method is private, so we exercise it via an
 * anonymous-class harness that uses the trait and re-exposes the method publicly —
 * the same pattern used by JsonSanitizerTraitTest.
 *
 * @since 2.2.0
 */
class ChecksumsBaseUrlConfigTest extends AbstractUnitTestCase
{
	private function makeSut(): object
	{
		return new class {
			use \Akeeba\Panopticon\Application\Trait\DefaultConfigurationTrait;

			public function validate(mixed $x): string
			{
				return $this->validateOptionalUrl($x);
			}
		};
	}

	public function testEmptyStringReturnsEmpty(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('', $sut->validate(''));
	}

	public function testWhitespaceOnlyStringReturnsEmpty(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('', $sut->validate('   '));
	}

	public function testValidHttpsUrlIsReturnedAsIs(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('https://example.com/sums', $sut->validate('https://example.com/sums'));
	}

	public function testValidHttpUrlIsReturnedAsIs(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('http://example.com/sums', $sut->validate('http://example.com/sums'));
	}

	public function testTrailingSlashIsTrimmed(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('https://example.com/sums', $sut->validate('https://example.com/sums/'));
	}

	public function testMultipleTrailingSlashesAreTrimmed(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('https://example.com/sums', $sut->validate('https://example.com/sums///'));
	}

	public function testNonUrlStringThrowsRuntimeException(): void
	{
		$sut = $this->makeSut();

		$this->expectException(RuntimeException::class);

		$sut->validate('foobar');
	}

	public function testFtpUrlThrowsRuntimeException(): void
	{
		$sut = $this->makeSut();

		$this->expectException(RuntimeException::class);

		$sut->validate('ftp://example.com/sums');
	}

	public function testLeadingWhitespaceIsTrimmedBeforeValidation(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('https://example.com/sums', $sut->validate('  https://example.com/sums  '));
	}
}
