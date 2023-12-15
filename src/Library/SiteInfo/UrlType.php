<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SiteInfo;


defined('AKEEBA') || die;

enum UrlType: int
{
	case EMBED_BASE64 = 0;

	case ABSOLUTE = 1;

	case ABSOLUTE_SCHEME = 2;

	case ABSOLUTE_PATH = 3;

	case RELATIVE = 4;
}
