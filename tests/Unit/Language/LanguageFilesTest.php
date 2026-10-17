<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Language;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use Awf\Container\Container;
use Awf\Text\Language;

/**
 * Checks the shipped INI language files for strings which display wrongly once AWF loads them.
 *
 * Every string is read through AWF's own Language class, exactly as the application does, so the assertions are
 * about the text the user actually gets to see.
 *
 * @since 2.4.0
 */
class LanguageFilesTest extends AbstractUnitTestCase
{
	/** @var array<string, array<string, string>> Displayed strings, per language code */
	private static array $strings = [];

	public static function languageProvider(): array
	{
		$ret = [];

		foreach (glob(APATH_ROOT . '/languages/*.ini') as $file)
		{
			$langCode       = basename($file, '.ini');
			$ret[$langCode] = [$langCode];
		}

		return $ret;
	}

	/**
	 * An apostrophe is a plain `'`. INI files do not escape it: `\'` is displayed with its backslash, and `''` as two
	 * apostrophes.
	 *
	 * @dataProvider languageProvider
	 */
	public function testApostrophesAreNotEscaped(string $langCode): void
	{
		$bad = array_filter(
			self::getStrings($langCode),
			fn(string $text) => str_contains($text, "\\'") || str_contains($text, "''")
		);

		$this->assertSame([], $bad, 'These strings display an escaped or doubled apostrophe.');
	}

	/**
	 * AWF only interprets the `\n`, `\t` and `\\` escape sequences, and `\"` while loading the file. Any other
	 * backslash is displayed as-is, and inside an HTML attribute it breaks the markup, e.g. `<a href=\"%s\">` makes the
	 * link point to `\"https://…\"` (gh-1081). Before a sprintf() placeholder such as `%1\$d` it makes sprintf() throw.
	 *
	 * @dataProvider languageProvider
	 */
	public function testNoStrayBackslashes(string $langCode): void
	{
		$bad = array_filter(
			self::getStrings($langCode),
			fn(string $text) => str_contains($text, '\\')
		);

		$this->assertSame([], $bad, 'These strings display a stray backslash.');
	}

	/**
	 * Typographic quotes are fine in the text, but HTML only accepts straight quotes around attribute values:
	 * `<a href=“%s”>` makes the link point to `“https://…”`.
	 *
	 * @dataProvider languageProvider
	 */
	public function testNoTypographicQuotesInsideHtmlTags(string $langCode): void
	{
		$bad = array_filter(
			self::getStrings($langCode),
			fn(string $text) => preg_match('#<[a-z/][^<>]*[“”„‟«»‘’‚‛″]#iu', $text)
		);

		$this->assertSame([], $bad, 'These strings use typographic quotes inside an HTML tag.');
	}

	/**
	 * Returns the strings of a language file the way the application displays them.
	 *
	 * @param   string  $langCode  The language code, e.g. en-GB
	 *
	 * @return  array<string, string>
	 */
	protected static function getStrings(string $langCode): array
	{
		if (isset(self::$strings[$langCode]))
		{
			return self::$strings[$langCode];
		}

		$keys     = [];
		$language = new Language(new Container(['application_name' => 'Panopticon']));

		$language->loadLanguage(
			$langCode, APATH_ROOT . '/languages', true, false,
			function (string $filename, array $strings) use (&$keys) {
				$keys = array_keys($strings);
			}
		);

		self::assertNotEmpty($keys, sprintf('Could not load the %s language file.', $langCode));

		self::$strings[$langCode] = array_combine(
			$keys,
			array_map(fn(string $key) => $language->text($key), $keys)
		);

		return self::$strings[$langCode];
	}
}
