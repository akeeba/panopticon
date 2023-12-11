<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;

defined('AKEEBA') || die;

/**
 * Represents the type of Content Management System (CMS).
 *
 * @since  1.0.6
 */
enum CMSType: string
{
	case JOOMLA    = 'joomla';
	case WORDPRESS = 'wordpress';
}
