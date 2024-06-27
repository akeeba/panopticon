<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\Site;

trait AdminToolsTrait
{
	protected function hasAdminTools(Site $site, bool $onlyProfessional = true)
	{
		if ($site->cmsType() === CMSType::JOOMLA)
		{
			// Joomla 3 doesn't support Download Keys, so we test the package name instead.
			if (version_compare($site->getConfig()->get('core.current.version', '4.0.0'), '3.99999.99999', 'le'))
			{
				return array_reduce(
					(array) $site->getConfig()->get('extensions.list'),
					fn(bool $carry, object $item) => $carry ||
					                                 (
						                                 $item->type === 'package'
						                                 && $item->element === 'pkg_admintools'
						                                 && (!$onlyProfessional || str_contains(strtolower($item->description), 'admin tools professional'))
					                                 ),
					false
				);
			}

			// Joomla 4 and later, we just check if the package supports download keys.
			return array_reduce(
				(array) $site->getConfig()->get('extensions.list'),
				fn(bool $carry, object $item) => $carry ||
				                                 (
					                                 $item->type === 'package'
					                                 && $item->element === 'pkg_admintools'
					                                 && (!$onlyProfessional || $item->downloadkey?->supported)
				                                 ),
				false
			);
		}

		if ($site->cmsType() === CMSType::WORDPRESS)
		{
			return array_reduce(
				(array) $site->getConfig()->get('extensions.list'),
				fn(bool $carry, object $item) => $carry ||
				                                 (
					                                 $item->type === 'plugin'
					                                 && $item->element === 'admintoolswp.php'
					                                 && (!$onlyProfessional || str_contains($item->name, 'Professional'))
				                                 ),
				false
			);
		}

		return false;
	}
}