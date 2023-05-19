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
	case MAIL = 'mail';
}
