#!/usr/bin/env php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * ini_to_po
 *
 * Converts INI language files to GNU GetText PO / POT format for translation.
 *
 * The en-GB language is converted to a POT file, which is the ground truth of language strings to be translated. All
 * other languages are converted to PO files, which is what translators work with.
 *
 * If the PO files already exist, their headers are kept to preserve continuity of the translation metadata. Any
 * comments, however, are lost. This is why this is only really used once when initialising the PO files from the INI
 * files originally in the repo, and periodically only to update the POT file.
 */

class Converter
{
	private static string $sourceFolder;

	private static array $original = [];

	private static array $translation = [];

	public static function setSourceFolder(string $sourceFolder): void
	{
		self::$sourceFolder = rtrim($sourceFolder, '/' . DIRECTORY_SEPARATOR);
	}

	public static function convertLanguage(string $langCode, bool $asPot = false): void
	{
		self::loadLanguage();
		self::loadLanguage($langCode, false);

		$potFile  = self::$sourceFolder . DIRECTORY_SEPARATOR . 'panopticon.pot';
		$filePath = $asPot ? $potFile : self::$sourceFolder . DIRECTORY_SEPARATOR . $langCode . '.po';

		@include_once __DIR__ . '/../version.php';
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

		foreach (self::$original as $orKey => $orValue)
		{
			$translation = $asPot ? '' : self::$translation[$orKey] ?? '';
			$content     .= "#, phpformat\n" . sprintf("msgctxt \"%s\"\n", $orKey) . sprintf(
					"msgid \"%s\"\n", addslashes($orValue)
				) . sprintf("msgstr \"%s\"\n", addslashes($translation)) . "\n";
		}

		file_put_contents($filePath, $content);
	}

	public static function convertAll(bool $convertToPot = false): void
	{
		$di = new DirectoryIterator(self::$sourceFolder);

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

			echo sprintf("%s\n", $langCode);
			self::convertLanguage($langCode, $asPot);
		}
	}

	private static function loadLanguage(string $langCode = 'en-GB', bool $original = true): void
	{
		$sourceFile = self::$sourceFolder . DIRECTORY_SEPARATOR . $langCode . '.ini';

		if (!file_exists($sourceFile) || !is_readable($sourceFile))
		{
			throw new ReflectionException(
				sprintf('Source file for %s language does not exist, or is not readable.', $langCode)
			);
		}

		$strings = parse_ini_file($sourceFile, false, INI_SCANNER_RAW);
		$strings = array_filter($strings, fn($x) => !empty($x) || !empty(trim($x)));

		if ($original)
		{
			self::$original = $strings;
		}
		else
		{
			self::$translation = $strings;
		}
	}

	private static function getPOHeader(string $filePath): ?string
	{
		if (!file_exists($filePath) || !file_exists($filePath))
		{
			return null;
		}

		$lines = file($filePath);
		// Remove comments
		$lines = array_filter($lines, fn($x) => !str_starts_with($x, '#'));
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

$year = gmdate('Y');
echo <<< TEXT
ini_to_po - Converts INI language files to GNU GetText PO/POT format
Copyright (C) 2023-{$year} Nicholas K. Dionysopoulos / Akeeba Ltd
License: GNU Affero General Public License, version 3 or later
         <https://www.gnu.org/licenses/agpl-3.0.txt>


TEXT;

if ($argc === 1)
{
	$basename = basename($argv[0]);
	echo <<< TEXT
Usage
  $basename [LANG] [--all] [--pot]

LANG
  The language to convert, e.g. el-GR.

--all
  Convert all languages.

--pot
  Generate the POT file.
 
Options are mutually exclusive. You can specify LANG *or* --all *or* --pot. 

TEXT;

	exit(1);
}

$all  = in_array('--all', $argv);
$pot  = in_array('--pot', $argv);
$lang = array_reduce(
	array_slice($argv, 1), function (?string $carry, string $item) {
	if ($carry)
	{
		return $carry;
	}

	if (str_starts_with($item, '-'))
	{
		return $carry;
	}

	return $item;
}
);

if (($all && ($pot || !empty($lang)))
    || ($pot && ($all || !empty($lang)))
    || (!empty($lang) && ($all || $pot)))
{
	echo <<< TEXT

Options are mutually exclusive. You can specify LANG *or* --all *or* --pot.

TEXT;

	exit(2);
}

Converter::setSourceFolder(realpath(__DIR__ . '/../languages'));

if ($all)
{
	echo "Converting all languages.\n";

	Converter::convertAll();
}
elseif ($pot)
{
	echo "Creating POT.\n";

	Converter::convertLanguage('en-GB', true);
}
else
{
	echo "Converting only $lang.\n";

	Converter::convertLanguage($lang, false);
}
