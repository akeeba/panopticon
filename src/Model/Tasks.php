<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

/**
 * Tasks Management Model
 *
 * @since  1.0.0
 */
class Tasks extends Task
{
	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$search = trim($this->getState('search', null) ?? '');

		if (!empty($search))
		{
			$query->extendWhere(
				'AND', [
				$query->quoteName('type') . ' LIKE ' . $query->quote('%' . $search . '%'),
			], 'OR'
			);
		}

		return $query;
	}

}