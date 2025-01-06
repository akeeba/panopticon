<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
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
	case UNKNOWN   = '';
	case JOOMLA    = 'joomla';
	case WORDPRESS = 'wordpress';

	public function forHumans(): string
	{
		return match ($this)
		{
			default => 'Unknown',
			self::JOOMLA => 'Joomla!&reg;',
			self::WORDPRESS => 'WordPress',
		};
	}

	public function logoClass(bool $fullClassList = false): string
	{
		if ($fullClassList)
		{
			return match ($this)
			{
				default => 'fa fa-square',
				self::JOOMLA => 'fab fa-joomla',
				self::WORDPRESS => 'fab fa-wordpress',
			};
		}

		return match ($this)
		{
			default => 'square',
			self::JOOMLA => 'joomla',
			self::WORDPRESS => 'wordpress',
		};
	}
}
