<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Queue;

defined('AKEEBA') || die;

enum QueueTypeEnum: string
{
	case MAIL = 'mail';

	case EXTENSIONS = 'extensions.%d';

	case PLUGINS = 'plugins.%d';
}
