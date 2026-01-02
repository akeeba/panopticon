#!/usr/bin/env php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Helper\LanguageTools;

const AKEEBA = 1;

require_once __DIR__ . '/../src/Helper/LanguageTools.php';

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

if ($all)
{
	echo "Converting all languages.\n";

	LanguageTools::convertAllToPo();
}
elseif ($pot)
{
	echo "Creating POT.\n";

	LanguageTools::convertToPo('en-GB');
}
else
{
	echo "Converting only $lang.\n";

	LanguageTools::convertToPo($lang);
}
