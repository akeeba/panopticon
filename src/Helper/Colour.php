<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

use Awf\Helper\AbstractHelper;

/**
 * Colour handling for group badges: hex sanitisation and WCAG contrast calculations.
 *
 * @since 2.2.1
 */
class Colour extends AbstractHelper
{
	/**
	 * The 16-colour standard palette offered by the group colour picker.
	 *
	 * Values are Bootstrap 5's own theme colours (plus black/white/silver) so the badges sit
	 * naturally next to the rest of the UI.
	 *
	 * @var  array<string, string>  hex => language key
	 * @since 2.2.1
	 */
	public const PALETTE = [
		'#0d6efd' => 'PANOPTICON_COLOUR_BLUE',
		'#6610f2' => 'PANOPTICON_COLOUR_INDIGO',
		'#6f42c1' => 'PANOPTICON_COLOUR_PURPLE',
		'#d63384' => 'PANOPTICON_COLOUR_PINK',
		'#dc3545' => 'PANOPTICON_COLOUR_RED',
		'#fd7e14' => 'PANOPTICON_COLOUR_ORANGE',
		'#ffc107' => 'PANOPTICON_COLOUR_YELLOW',
		'#198754' => 'PANOPTICON_COLOUR_GREEN',
		'#20c997' => 'PANOPTICON_COLOUR_TEAL',
		'#0dcaf0' => 'PANOPTICON_COLOUR_CYAN',
		'#6c757d' => 'PANOPTICON_COLOUR_GREY',
		'#adb5bd' => 'PANOPTICON_COLOUR_SILVER',
		'#343a40' => 'PANOPTICON_COLOUR_DARK',
		'#f8f9fa' => 'PANOPTICON_COLOUR_LIGHT',
		'#000000' => 'PANOPTICON_COLOUR_BLACK',
		'#ffffff' => 'PANOPTICON_COLOUR_WHITE',
	];

	/**
	 * The current "no colour" badge background (Bootstrap's `bg-secondary`).
	 *
	 * @since 2.2.1
	 */
	private const FALLBACK_COLOUR = '#6c757d';

	/**
	 * Bootstrap's actual `text-light` colour, used for contrast calculations.
	 *
	 * @since 2.2.1
	 */
	private const TEXT_LIGHT = '#f8f9fa';

	/**
	 * Bootstrap's actual `text-dark` colour, used for contrast calculations.
	 *
	 * @since 2.2.1
	 */
	private const TEXT_DARK = '#212529';

	/**
	 * The single validation gate for colour strings. Nothing else in the codebase may build a
	 * colour string that has not passed through here.
	 *
	 * Accepts `#rgb`, `rgb`, `#rrggbb`, `rrggbb` (case-insensitively), expands 3-digit forms to
	 * 6-digit, and returns a lowercase `#rrggbb` string. Returns NULL for empty input or anything
	 * that is not a valid hex colour.
	 *
	 * @param   string|null  $hex  The raw, untrusted colour string.
	 *
	 * @return  string|null  A sanitised `#rrggbb` string, or NULL.
	 * @since   2.2.1
	 */
	public function sanitise(?string $hex): ?string
	{
		$hex = trim($hex ?? '');

		if ($hex === '')
		{
			return null;
		}

		$hex = ltrim($hex, '#');

		if (preg_match('/^[0-9a-fA-F]{3}$/', $hex) === 1)
		{
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1)
		{
			return null;
		}

		return '#' . strtolower($hex);
	}

	/**
	 * Calculate the WCAG 2.x relative luminance of a colour.
	 *
	 * @param   string  $hex  A colour string; passed through {@see sanitise()} first.
	 *
	 * @return  float
	 * @since   2.2.1
	 */
	public function relativeLuminance(string $hex): float
	{
		$hex = $this->sanitise($hex) ?? self::FALLBACK_COLOUR;

		[$r, $g, $b] = array_map(
			fn(string $channel): float => $this->linearise((int) hexdec($channel) / 255),
			[substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]
		);

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * Calculate the WCAG 2.x contrast ratio between two colours.
	 *
	 * @param   string  $a  The first colour; passed through {@see sanitise()} first.
	 * @param   string  $b  The second colour; passed through {@see sanitise()} first.
	 *
	 * @return  float
	 * @since   2.2.1
	 */
	public function contrastRatio(string $a, string $b): float
	{
		$lA = $this->relativeLuminance($a);
		$lB = $this->relativeLuminance($b);

		$lighter = max($lA, $lB);
		$darker  = min($lA, $lB);

		return ($lighter + 0.05) / ($darker + 0.05);
	}

	/**
	 * Pick the Bootstrap foreground text class (`text-light` or `text-dark`) giving the higher
	 * contrast ratio against the given background colour.
	 *
	 * @param   string|null  $hex  The background colour; NULL means "no colour" (today's
	 *                              `bg-secondary` badge), which returns `text-light`.
	 *
	 * @return  string  Either `text-light` or `text-dark`.
	 * @since   2.2.1
	 */
	public function foregroundClass(?string $hex): string
	{
		if ($hex === null)
		{
			return 'text-light';
		}

		$hex = $this->sanitise($hex) ?? self::FALLBACK_COLOUR;

		$contrastWithLight = $this->contrastRatio($hex, self::TEXT_LIGHT);
		$contrastWithDark  = $this->contrastRatio($hex, self::TEXT_DARK);

		return $contrastWithLight >= $contrastWithDark ? 'text-light' : 'text-dark';
	}

	/**
	 * Linearise a single sRGB colour channel per the WCAG 2.x relative luminance formula.
	 *
	 * @param   float  $c  The channel value, normalised to the [0, 1] range.
	 *
	 * @return  float
	 * @since   2.2.1
	 */
	private function linearise(float $c): float
	{
		return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
	}
}
