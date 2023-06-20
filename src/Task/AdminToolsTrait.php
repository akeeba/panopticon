<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;


use Akeeba\Panopticon\Model\Site;

defined('AKEEBA') || die;

trait AdminToolsTrait
{
	protected function hasAdminTools(Site $site, bool $onlyProfessional = true)
	{
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
}