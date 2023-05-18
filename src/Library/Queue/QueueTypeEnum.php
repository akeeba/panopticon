<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Queue;

defined('AKEEBA') || die;

enum QueueTypeEnum: string
{
	/**
	 * Retrieve update and environment information for a site.
	 */
	case FETCH_INFO = 'fetch';

	/**
	 * Joomla! core updates
	 */
	case CORE_UPDATE = 'core_update';

	/**
	 * Joomla! extension update
	 */
	case EXTENSIONS_UPDATE = 'extensions_update';

	/**
	 * Take a backup
	 */
	case BACKUP = 'backup';
}
