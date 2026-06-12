<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Tests for JsonSanitizerTrait — exercises sanitizeJson() and jsonValidate() via an anonymous
 * class harness that uses the trait and re-exposes its private methods as public pass-throughs.
 *
 * @since 1.4.0
 */
class JsonSanitizerTraitTest extends AbstractUnitTestCase
{
	private function makeSut(): object
	{
		return new class {
			use \Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;

			public function sanitize(string $raw): string
			{
				return $this->sanitizeJson($raw);
			}

			public function valid(string $json): bool
			{
				return $this->jsonValidate($json);
			}
		};
	}

	// ── sanitizeJson ─────────────────────────────────────────────────────────

	public function testValidJsonObjectPassesThroughUnchanged(): void
	{
		$sut = $this->makeSut();
		$input = '{"a":1}';

		$this->assertSame($input, $sut->sanitize($input));
	}

	public function testValidJsonArrayPassesThroughUnchanged(): void
	{
		$sut = $this->makeSut();
		$input = '[1,2,3]';

		$this->assertSame($input, $sut->sanitize($input));
	}

	public function testEmptyStringReturnsRawInput(): void
	{
		$sut = $this->makeSut();

		$this->assertSame('', $sut->sanitize(''));
	}

	public function testWhitespaceOnlyStringReturnsRawInput(): void
	{
		$sut = $this->makeSut();
		$input = '   ';

		$this->assertSame($input, $sut->sanitize($input));
	}

	public function testJoomlaCheatExtractsFromLinksPrefix(): void
	{
		$sut = $this->makeSut();
		$junk = 'PHP Notice: something happened' . "\n";
		$validJson = '{"links":{"self":"x"},"data":[]}';

		$result = $sut->sanitize($junk . $validJson);

		$this->assertSame($validJson, $result);
	}

	public function testObjectPrefixedByJunkIsRecovered(): void
	{
		$sut = $this->makeSut();
		$input = 'PHP Warning: blah {"ok":true}';

		$this->assertSame('{"ok":true}', $sut->sanitize($input));
	}

	public function testArrayPrefixedByJunkIsRecovered(): void
	{
		$sut = $this->makeSut();
		$input = 'noticeXYZ[1,2,3]';

		$this->assertSame('[1,2,3]', $sut->sanitize($input));
	}

	public function testUnrecoverableInputReturnsRawString(): void
	{
		$sut = $this->makeSut();
		$input = 'not json at all';

		$this->assertSame($input, $sut->sanitize($input));
	}

	// ── jsonValidate ─────────────────────────────────────────────────────────

	public function testJsonValidateReturnsTrueForValidObject(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->valid('{"a":1}'));
	}

	public function testJsonValidateReturnsFalseForInvalidString(): void
	{
		$sut = $this->makeSut();

		$this->assertFalse($sut->valid('nope'));
	}

	public function testJsonValidateReturnsTrueForNullLiteral(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->valid('null'));
	}
}
