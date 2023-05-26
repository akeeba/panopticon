<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

enum DarkModeEnum: int
{
	case APPLICATION = 0;

	case BROWSER = 1;

	case LIGHT = 2;

	case DARK = 3;
}
