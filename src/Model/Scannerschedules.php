<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

/**
 * Scanner Schedules Management Model
 *
 * @since  1.3.4
 */
class Scannerschedules extends Tasks
{
	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$query->where($query->quoteName('type') . ' = ' . $query->quote('filescanner'));

		$site_id = $this->getState('site_id', null);

		if (!empty($site_id))
		{
			$query->where($query->quoteName('site_id') . ' = ' . (int)$site_id);
		}

		return $query;
	}
}
