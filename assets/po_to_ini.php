#!/usr/bin/env php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Helper\LanguageTools;

const AKEEBA = 1;

require_once __DIR__ . '/../src/Helper/LanguageTools.php';

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

	LanguageTools::convertToIni($lang);

	exit;
}

LanguageTools::convertAllToIni();