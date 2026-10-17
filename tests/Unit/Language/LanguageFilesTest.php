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
	 * A double quote inside a value is always written `\"`. A bare `"` happens to work with AWF, which reads the file
	 * in raw mode, but PHP's standard INI parser and translation tools choke on it.
	 *
	 * @dataProvider languageProvider
	 */
	public function testDoubleQuotesAreEscaped(string $langCode): void
	{
		$bad = [];

		foreach (file(APATH_ROOT . '/languages/' . $langCode . '.ini') as $lineNumber => $line)
		{
			if (preg_match('/^([A-Z0-9_]+)="(.*)"\s*$/', $line, $matches) && preg_match('/(?<!\\\\)"/', $matches[2]))
			{
				$bad[$lineNumber + 1] = $matches[1];
			}
		}

		$this->assertSame([], $bad, 'These lines have a double quote which is not escaped as \".');
	}

	/**
	 * The file must also be readable by PHP's standard INI parser, not only by AWF's raw mode reading.
	 *
	 * @dataProvider languageProvider
	 */
	public function testStandardIniParserCanReadTheFile(string $langCode): void
	{
		$this->assertNotFalse(
			@parse_ini_file(APATH_ROOT . '/languages/' . $langCode . '.ini'),
			sprintf('PHP cannot parse %s.ini: %s', $langCode, error_get_last()['message'] ?? '')
		);
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
	 * A translation must use the same sprintf() placeholders as en-GB, though not necessarily in the same order. A
	 * missing placeholder loses information; a malformed one (`% is`, `%$`) makes sprintf() throw, so the user sees
	 * "BAD TRANSLATION" instead of the text.
	 *
	 * A plural form (`KEY_1`, `KEY_2`, …) may instead use the placeholders of the base key, as Slavic languages need
	 * plural forms which en-GB does not have.
	 *
	 * @dataProvider languageProvider
	 */
	public function testPlaceholdersMatchEnglish(string $langCode): void
	{
		$english = self::getStrings('en-GB');
		$bad     = [];

		foreach (self::getStrings($langCode) as $key => $text)
		{
			$baseKey   = preg_replace('/_\d+$/', '', $key);
			$allowed   = [];
			$allowed[] = isset($english[$key]) ? self::getPlaceholders($english[$key]) : null;
			$allowed[] = ($baseKey !== $key && isset($english[$baseKey]))
				? self::getPlaceholders($english[$baseKey]) : null;
			$allowed   = array_filter($allowed, fn($x) => $x !== null);

			if (empty($allowed) || in_array(self::getPlaceholders($text), $allowed, true))
			{
				continue;
			}

			$bad[$key] = $text;
		}

		$this->assertSame([], $bad, 'These strings do not use the same sprintf() placeholders as en-GB.');
	}

	/**
	 * A translation must use the same HTML tags as en-GB, attributes included, though not necessarily in the same
	 * order. Different markup means a lost link or formatting, or a translation of an older or shortened text.
	 *
	 * The values of the title, alt, aria-label and placeholder attributes are text, and translated.
	 *
	 * @dataProvider languageProvider
	 */
	public function testHtmlTagsMatchEnglish(string $langCode): void
	{
		$english = self::getStrings('en-GB');
		$bad     = [];

		foreach (self::getStrings($langCode) as $key => $text)
		{
			if (isset($english[$key]) && self::getHtmlTags($text) !== self::getHtmlTags($english[$key]))
			{
				$bad[$key] = $text;
			}
		}

		$this->assertSame([], $bad, 'These strings do not use the same HTML tags as en-GB.');
	}

	/**
	 * Every HTML attribute in en-GB must be one the browser understands. A misspelt one, such as `_target`, is silently
	 * ignored. The translations must use the same tags as en-GB, so this is only checked in en-GB.
	 */
	public function testEnglishHtmlAttributesAreValid(): void
	{
		$known = [
			'alt', 'aria-label', 'class', 'href', 'id', 'lang', 'placeholder', 'rel', 'role', 'style', 'target', 'title',
		];
		$bad   = [];

		foreach (self::getStrings('en-GB') as $key => $text)
		{
			preg_match_all('#<[a-z][^<>]*>#i', $text, $tags);

			foreach ($tags[0] as $tag)
			{
				preg_match_all('/\s([^\s=<>"\']+)\s*=/', $tag, $attributes);

				$unknown = array_filter(
					$attributes[1],
					fn(string $attribute) => !in_array(strtolower($attribute), $known, true)
						&& !str_starts_with($attribute, 'data-')
				);

				if (!empty($unknown))
				{
					$bad[$key] = $text;
				}
			}
		}

		$this->assertSame([], $bad, 'These strings use an unknown HTML attribute.');
	}

	/**
	 * Returns the HTML tags of a string, sorted, with the values of translatable attributes blanked out.
	 *
	 * @param   string  $text  The string to examine
	 *
	 * @return  string[]
	 */
	private static function getHtmlTags(string $text): array
	{
		preg_match_all('#<[a-z/][^<>]*>#i', $text, $matches);

		$ret = array_map(
			fn(string $tag) => preg_replace('/\b(title|alt|aria-label|placeholder)="[^"]*"/', '$1=""', $tag),
			$matches[0]
		);

		sort($ret);

		return $ret;
	}

	/**
	 * Returns the sprintf() placeholders of a string, normalised and sorted, e.g. `%s from %s` and `%2$s from %1$s`
	 * both give `['1:s', '2:s']`. A `%` which does not start a valid placeholder is returned verbatim with the
	 * character after it.
	 *
	 * @param   string  $text  The string to examine
	 *
	 * @return  string[]
	 */
	private static function getPlaceholders(string $text): array
	{
		$ret  = [];
		$next = 1;

		preg_match_all(
			'/%(?:%|(?:(\d+)\$)?[-+ 0]*(?:\'.)?\d*(?:\.\d+)?([bcdeEfFgGosuxX])|.?)/u', $text, $matches, PREG_SET_ORDER
		);

		foreach ($matches as $match)
		{
			if ($match[0] === '%%')
			{
				continue;
			}

			if (!isset($match[2]) || $match[2] === '')
			{
				$ret[] = $match[0];

				continue;
			}

			$argument = $match[1] !== '' ? (int) $match[1] : $next++;
			$ret[]    = $argument . ':' . $match[2];
		}

		$ret = array_unique($ret);
		sort($ret);

		return $ret;
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
		$language = new Language(
			new Container(
				[
					'application_name'     => 'Panopticon',
					'applicationNamespace' => 'Akeeba\\Panopticon',
					'languagePath'         => APATH_ROOT . '/languages',
					'session_segment_name' => 'panopticon_language_test',
				]
			)
		);

		$language->loadLanguage(
			$langCode, null, true, false,
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
