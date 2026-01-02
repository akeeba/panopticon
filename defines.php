<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

// Are we running in Docker?
define('APATH_IN_DOCKER', !file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '.not_docker'));

const APATH_BASE = __DIR__;
const APATH_ROOT = APATH_BASE;
const APATH_SITE = APATH_ROOT;

if (APATH_IN_DOCKER)
{
	define('APATH_CONFIGURATION', APATH_ROOT . '/config');

	if (!file_exists(APATH_CONFIGURATION))
	{
		mkdir(APATH_CONFIGURATION, 0755);
	}
}
else
{
	define('APATH_CONFIGURATION', APATH_ROOT);
}

const APATH_MEDIA       = APATH_BASE . DIRECTORY_SEPARATOR . 'media';
const APATH_THEMES      = APATH_BASE . DIRECTORY_SEPARATOR . 'templates';
const APATH_TRANSLATION = APATH_BASE . DIRECTORY_SEPARATOR . 'languages';
const APATH_CACHE       = APATH_BASE . DIRECTORY_SEPARATOR . 'cache';
const APATH_TMP         = APATH_BASE . DIRECTORY_SEPARATOR . 'tmp';
const APATH_LOG         = APATH_BASE . DIRECTORY_SEPARATOR . 'log';
const APATH_PLUGIN      = APATH_BASE . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Plugin';
const APATH_USER_CODE   = APATH_BASE . DIRECTORY_SEPARATOR . 'user_code';