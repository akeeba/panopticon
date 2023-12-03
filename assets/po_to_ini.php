#!/usr/bin/env php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * po_to_ini
 *
 * Converts a GNU GetText PO file to the INI language format.
 *
 * The conversion is based on the assumption that the message context of each translated message contains the INI key,
 * which is exactly how our PO files are created and managed (assuming translators used Poedit, as told).
 *
 * This is meant to be used automatically, through the build scripts. Eventually, we will have to integrate it to
 * Panopticon itself to aid translators.
 */

function convertPoToIni(string $sourceFile)
{
	// Get the language code
	$langCode = basename($sourceFile, '.po');

	// Read the file into lines
	$lines = file($sourceFile);

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

	$outfile = dirname($sourceFile) . DIRECTORY_SEPARATOR . $langCode . '.ini';

	file_put_contents($outfile, $content);
}

$year = gmdate('Y');
echo <<< TEXT
po_to_ini - Converts GNU GetText PO/POT files to INI language format
Copyright (C) 2023-{$year} Nicholas K. Dionysopoulos / Akeeba Ltd
License: GNU Affero General Public License, version 3 or later
         <https://www.gnu.org/licenses/agpl-3.0.txt>


TEXT;

if ($argc === 1)
{
	$basename = basename($argv[0]);
	echo <<< TEXT
Usage
  $basename [LANG] [--all]

LANG
  The language to convert, e.g. el-GR.

--all
  Convert all languages.

Options are mutually exclusive. You can specify LANG *or* --all. 

TEXT;

	exit(1);
}

$all  = in_array('--all', $argv);
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

if ($all && !empty($lang))
{
	echo <<< TEXT

Options are mutually exclusive. You can specify LANG *or* --all.

TEXT;

	exit(2);
}

if (!empty($lang))
{
	echo "Converting $lang.\n";

	convertPoToIni(realpath(sprintf("%s/../languages/%s.po", __DIR__, $lang)));

	exit;
}

$di = new DirectoryIterator(realpath(__DIR__ . '/../languages'));

/** @var DirectoryIterator $file */
foreach ($di as $file)
{
	if (!$file->isFile() || $file->isDot() || $file->getExtension() != 'po')
	{
		continue;
	}

	$lang = $file->getBasename('.po');

	echo "Converting $lang.\n";

	convertPoToIni($file->getPathname());
}