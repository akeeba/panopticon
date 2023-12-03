<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

use DirectoryIterator;
use RuntimeException;

defined('AKEEBA') || die;

/**
 * Tools for converting language files between GNU GetText PO and INI formats.
 *
 * @since   1.0.6
 */
class LanguageTools
{
	/**
	 * Converts a specific PO language or absolute filepath to INI format.
	 *
	 * @param   string  $langOrFile
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public static function convertToIni(string $langOrFile): void
	{
		// Get the language code
		$langCode = basename($langOrFile, '.po');

		if (!file_exists($langOrFile))
		{
			$langOrFile = self::getSourceFolder() . DIRECTORY_SEPARATOR . $langCode . '.po';
		}

		if (!file_exists($langOrFile) || !is_readable($langOrFile))
		{
			return;
		}

		// Read the file into lines
		$lines = file($langOrFile);

		// Remove comments
		$lines = array_filter($lines, fn($x) => !str_starts_with($x, '#'));
		// Add a newline which will allow us to finalize the last chunk
		$lines[] = '';

		// Parse
		$strings     = [];
		$key         = null;
		$translation = null;
		$insideValue = false;

		foreach ($lines as $line)
		{
			$line = trim($line ?? '');

			if (empty($line))
			{
				// Commit value if key valid and translation not empty
				if (!empty($key) && $key === strtoupper($key) && !empty(trim($translation)))
				{
					$strings[trim($key)] = $translation;
				}

				$key         = null;
				$translation = null;
				$insideValue = false;

				continue;
			}

			// Ignore comments and msgid; we don't use them
			if (str_starts_with($line, '#') || str_starts_with($line, 'msgid'))
			{
				$insideValue = false;
				continue;
			}

			// The msgctxt is our lang key
			if (str_starts_with($line, 'msgctxt'))
			{
				$insideValue = false;
				$key         = trim(substr($line, 7));
				$key         = trim($key, '"');

				continue;
			}

			// The msgstr is our translation value
			if (str_starts_with($line, 'msgstr'))
			{
				$insideValue = true;
				$translation = trim(mb_substr($line, 6, encoding: 'UTF-8'));
				$translation = trim($translation, '"');
				$translation = stripcslashes($translation);

				continue;
			}

			if ($insideValue)
			{
				$more = trim(trim($line), '"');
				$more = stripcslashes($more);

				$translation .= $more;
			}
		}

		// Export to INI format
		$content = '';

		foreach ($strings as $k => $v)
		{
			$v = str_replace("\n", '\n', $v);
			$v = str_replace("\r", '\r', $v);
			$v = str_replace("\"", '\\"', $v);

			$content .= sprintf("%s=\"%s\"\n", $k, $v);
		}

		$outfile = dirname($langOrFile) . DIRECTORY_SEPARATOR . $langCode . '.ini';

		// Avoid writing an identical file
		if (@is_readable($outfile) && hash_file('sha256', $outfile) === hash('sha256', $content))
		{
			return;
		}

		file_put_contents($outfile, $content);
	}

	/**
	 * Converts a specific language or file from INI format to PO format.
	 *
	 * @param   string  $langOrFile  The language code or absolute filepath to convert.
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public static function convertToPo(string $langOrFile): void
	{
		$asPot           = basename($langOrFile, '.ini') === 'en-GB';
		$langOriginal    = self::loadIniLanguage();
		$langTranslation = $asPot ? $langOriginal : self::loadIniLanguage($langOrFile);

		if ($langOriginal === null || $langTranslation == null)
		{
			return;
		}

		$potFile  = self::getSourceFolder() . DIRECTORY_SEPARATOR . 'panopticon.pot';
		$filePath = $asPot ? $potFile : self::getSourceFolder() . DIRECTORY_SEPARATOR . $langOrFile . '.po';

		if (!defined('AKEEBA_PANOPTICON_VERSION'))
		{
			@include_once __DIR__ . '/../../version.php';
		}

		$version      = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev';
		$revisionDate = gmdate('Y-m-d H:i:s');

		if ($asPot)
		{
			$content = <<< POT
msgid ""
msgstr ""
"Project-Id-Version: $version\\n"
"POT-Creation-Date: $revisionDate\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator: Panopticon ini_to_po $version\\n"


POT;
		}
		else
		{
			$content = self::getPOHeader($filePath) ?? <<< TEXT
msgid ""
msgstr ""
"Project-Id-Version: $version\\n"
"POT-Creation-Date: $revisionDate\\n"
"PO-Revision-Date: $revisionDate\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator: Panopticon ini_to_po $version\\n"


TEXT;
		}

		foreach ($langOriginal as $orKey => $orValue)
		{
			$translation = $asPot ? '' : $langTranslation[$orKey] ?? '';
			$content     .= "#, phpformat\n" . sprintf("msgctxt \"%s\"\n", $orKey) . sprintf(
					"msgid \"%s\"\n", addslashes($orValue)
				) . sprintf("msgstr \"%s\"\n", addslashes($translation)) . "\n";
		}

		// Avoid writing an identical file
		if (@is_readable($filePath) && hash_file('sha256', $filePath) === hash('sha256', $content))
		{
			return;
		}

		file_put_contents($filePath, $content);
	}

	/**
	 * Converts all INI files to PO files
	 *
	 * @param   bool  $convertToPot  Should I generate a POT file for the en-GB language as well?
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public static function convertAllToPo(bool $convertToPot = false): void
	{
		$di = new DirectoryIterator(self::getSourceFolder());

		/** @var DirectoryIterator $file */
		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->isDot() || $file->getExtension() != 'ini')
			{
				continue;
			}

			$langCode = $file->getBasename('.ini');
			$asPot    = $langCode === 'en-GB';

			if ($asPot && !$convertToPot)
			{
				continue;
			}

			self::convertToPo($langCode);
		}
	}

	/**
	 * Converts all PO files to INI files
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public static function convertAllToIni(): void
	{
		$di = new DirectoryIterator(self::getSourceFolder());

		/** @var DirectoryIterator $file */
		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->isDot() || $file->getExtension() != 'po')
			{
				continue;
			}

			$langCode = $file->getBasename('.ini');

			if ($langCode === 'en-GB' || $langCode === 'panopticon' || !str_contains($langCode, '-'))
			{
				continue;
			}

			self::convertToIni($langCode);
		}
	}

	/**
	 * Returns the absolute filepath of the language folder, without looking it up in the container.
	 *
	 * @return  string
	 * @since   1.0.6
	 */
	private static function getSourceFolder(): string
	{
		return realpath(__DIR__ . '/../../languages');
	}

	/**
	 * Loads an INI language string to an array of strings
	 *
	 * @param   string  $langCode  The language code, or absolute filename to an INI file, to load.
	 *
	 * @return  array|null  The array of language strings. NULL if it does not exist.
	 * @since   1.0.6
	 */
	private static function loadIniLanguage(string $langCode = 'en-GB'): ?array
	{
		$sourceFile = self::getSourceFolder() . DIRECTORY_SEPARATOR . $langCode . '.ini';

		if (file_exists($langCode) && str_ends_with($sourceFile, '.ini'))
		{
			$sourceFile = $langCode;
		}

		if (!file_exists($sourceFile) || !is_readable($sourceFile))
		{
			return null;
		}

		$sourceContent = file_get_contents($sourceFile);

		if ($sourceContent === false)
		{
			return null;
		}

		$sourceContent = str_replace(
			['"_QQ_"', '\\"_QQ_\\"'],
			['"', '"'],
			$sourceContent
		);

		return array_filter(
			parse_ini_string($sourceContent, false, INI_SCANNER_RAW),
			fn($x) => !empty($x) || !empty(trim($x))
		);
	}

	/**
	 * Get the header of a PO file, if it exists
	 *
	 * @param   string  $filePath  The full pathname to the PO file.
	 *
	 * @return  string|null  The file header. NULL if it does not exist.
	 * @since   1.0.6
	 */
	private static function getPOHeader(string $filePath): ?string
	{
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			return null;
		}

		$lines = file($filePath) ?: [];
		// Remove comments
		$lines = array_filter($lines, fn($x) => !str_starts_with($x, '#')) ?: [];
		// Add a newline which will allow us to finalize the last chunk
		$lines[] = '';

		// Parse
		$header      = null;
		$key         = null;
		$translation = null;
		$insideValue = false;

		foreach ($lines as $line)
		{
			$line = trim($line ?? '');

			if (empty($line))
			{
				if (empty($key))
				{
					$header = trim($translation ?? '') ?: null;

					break;
				}

				$key         = null;
				$translation = null;
				$insideValue = false;

				continue;
			}

			// Ignore comments and msgctxt; we don't use them
			if (str_starts_with($line, '#') || str_starts_with($line, 'msgctxt'))
			{
				$insideValue = false;
				continue;
			}

			// The msgid is our lang key
			if (str_starts_with($line, 'msgid'))
			{
				$insideValue = false;
				$key         = trim(substr($line, 5));
				$key         = trim($key, '"');

				continue;
			}

			// The msgstr is our translation value
			if (str_starts_with($line, 'msgstr'))
			{
				$insideValue = true;
				$translation = trim(mb_substr($line, 6, encoding: 'UTF-8'));
				$translation = trim($translation, '"');
				$translation = stripcslashes($translation);

				continue;
			}

			if ($insideValue)
			{
				$more = trim(trim($line), '"');
				$more = stripcslashes($more);

				$translation .= $more;
			}
		}

		if (empty($header))
		{
			return null;
		}

		$ret = "msgid \"\"\nmsgstr \"\"\n";
		foreach (explode("\n", $header) as $line)
		{
			$ret .= '"' . addslashes(trim($line)) . "\\n\"\n";
		}
		$ret .= "\n";

		return $ret;
	}
}