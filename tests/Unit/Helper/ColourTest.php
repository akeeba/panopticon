<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Helper;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Colour;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Tests for the Colour helper — the single validation gate for group badge colours (gh-1023).
 *
 * `sanitise()` is the only place in the codebase allowed to build a colour string; a junk or
 * malicious value (`javascript:…`, a truncated hex code, a CSS colour keyword) must be rejected
 * as NULL rather than reach a `style` attribute. `foregroundClass()` must satisfy the actual W3C
 * requirement of picking whichever of `text-light`/`text-dark` has the higher contrast ratio
 * against the badge background, for every colour in the standard palette.
 *
 * @since 2.2.1
 */
class ColourTest extends AbstractUnitTestCase
{
	private function makeColour(): Colour
	{
		return new Colour();
	}

	public function testSanitiseExpandsThreeDigitHexToSixDigit(): void
	{
		$this->assertSame('#aabbcc', $this->makeColour()->sanitise('#abc'));
	}

	public function testSanitiseLowercasesSixDigitHexWithHash(): void
	{
		$this->assertSame('#aabbcc', $this->makeColour()->sanitise('#AABBCC'));
	}

	public function testSanitiseAcceptsSixDigitHexWithoutHash(): void
	{
		$this->assertSame('#aabbcc', $this->makeColour()->sanitise('aabbcc'));
	}

	public function testSanitiseAcceptsThreeDigitHexWithoutHash(): void
	{
		$this->assertSame('#aabbcc', $this->makeColour()->sanitise('abc'));
	}

	/**
	 * @dataProvider invalidColourProvider
	 */
	public function testSanitiseReturnsNullForInvalidInput(?string $input): void
	{
		$this->assertNull($this->makeColour()->sanitise($input));
	}

	public static function invalidColourProvider(): array
	{
		return [
			'empty string'     => [''],
			'null'             => [null],
			'CSS keyword'      => ['red'],
			'five-digit hex'   => ['#12345'],
			'invalid hex char' => ['#gggggg'],
			'JS injection'     => ['javascript:alert(1)'],
		];
	}

	public function testContrastRatioOfBlackAndWhiteIsTwentyOne(): void
	{
		$ratio = $this->makeColour()->contrastRatio('#ffffff', '#000000');

		$this->assertEqualsWithDelta(21.0, $ratio, 0.001);
	}

	public function testForegroundClassOfYellowIsTextDark(): void
	{
		$this->assertSame('text-dark', $this->makeColour()->foregroundClass('#ffc107'));
	}

	public function testForegroundClassOfBlueIsTextLight(): void
	{
		$this->assertSame('text-light', $this->makeColour()->foregroundClass('#0d6efd'));
	}

	public function testForegroundClassOfDarkIsTextLight(): void
	{
		$this->assertSame('text-light', $this->makeColour()->foregroundClass('#212529'));
	}

	public function testForegroundClassOfNullIsTextLight(): void
	{
		$this->assertSame('text-light', $this->makeColour()->foregroundClass(null));
	}

	public function testEveryPaletteColourGetsTheHigherContrastForegroundClass(): void
	{
		$colour = $this->makeColour();

		foreach (array_keys(Colour::PALETTE) as $hex)
		{
			$contrastWithLight = $colour->contrastRatio($hex, '#f8f9fa');
			$contrastWithDark  = $colour->contrastRatio($hex, '#212529');

			$expected = $contrastWithLight >= $contrastWithDark ? 'text-light' : 'text-dark';

			$this->assertSame(
				$expected,
				$colour->foregroundClass($hex),
				sprintf('Palette colour %s did not get the higher-contrast foreground class.', $hex)
			);
		}
	}
}
