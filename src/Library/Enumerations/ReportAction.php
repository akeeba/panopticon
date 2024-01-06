<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;

defined('AKEEBA') || die;

/**
 * Report action enumeration
 *
 * @since  1.0.4
 */
enum ReportAction: string
{
	case CORE_UPDATE_FOUND     = 'core_update_found';
	case CORE_UPDATE_INSTALLED = 'core_update_installed';
	case EXT_UPDATE_FOUND      = 'ext_update_found';
	case EXT_UPDATE_INSTALLED  = 'ext_update_installed';
	case BACKUP                = 'backup';
	case FILESCANNER           = 'filescanner';
	case SITE_ACTION           = 'site_action';
	case UPTIME_UP             = 'site_up';
	case UPTIME_DOWN           = 'site_down';
	case MISC_ACTION           = 'misc_action';
}
