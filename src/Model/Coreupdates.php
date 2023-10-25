<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

class Coreupdates extends Site
{
	public function buildQuery($overrideLimits = false)
	{
		// Get a "select all" query
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($this->getTableName());

		// Run the "before build query" hook and behaviours
		if (method_exists($this, 'onBeforeBuildQuery'))
		{
			$this->onBeforeBuildQuery($query);
		}

		$this->behavioursDispatcher->trigger('onBeforeBuildQuery', [&$this, &$query]);

		// Apply custom WHERE clauses
		if (count($this->whereClauses))
		{
			foreach ($this->whereClauses as $clause)
			{
				$query->where($clause);
			}
		}

		// Apply ordering unless we are called to override limits
		if (!$overrideLimits)
		{
			$order = $this->getState('filter_order', null, 'cmd');

			if (!array_key_exists($order, $this->knownFields))
			{
				$order = $this->getIdFieldName();
			}

			$order = $db->qn($order);

			$dir = strtoupper($this->getState('filter_order_Dir', 'ASC', 'cmd'));

			if (!in_array($dir, ['ASC', 'DESC']))
			{
				$dir = 'ASC';
			}

			$query->order($order . ' ' . $dir);
		}

		// Run the "before after query" hook and behaviours
		if (method_exists($this, 'onAfterBuildQuery'))
		{
			$this->onAfterBuildQuery($query);
		}

		$this->behavioursDispatcher->trigger('onAfterBuildQuery', [&$this, &$query]);

		// Only return sites with updates
		$query
			->where(
				[
					$db->quoteName('enabled') . ' = 1',
					$query->jsonPointer('config', '$.core.current.version') . ' IS NOT NULL',
					$query->jsonPointer('config', '$.core.latest.version') . ' IS NOT NULL',
					$query->jsonPointer('config', '$.core.current.version')
					. ' != ' . $query->jsonPointer('config', '$.core.latest.version'),
				]
			);

		// Filters: site
		$fltSiteId = $this->getState('site_id', 0, 'int');

		if ($fltSiteId > 0)
		{
			$query->where($db->quoteName('id') . ' = ' . $db->quote($fltSiteId));
		}

		// Filters: current CMS family
		$fltCmsFamily = $this->getState('cmsFamily', null, 'cmd');

		if ($fltCmsFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.current.version') . ' LIKE ' .
				$query->quote('"' . $fltCmsFamily . '.%')
			);
		}

		// Filters: latest CMS family
		$fltLatestFamily = $this->getState('latestFamily', null, 'cmd');

		if ($fltLatestFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.latest.version') . ' LIKE ' .
				$query->quote('"' . $fltLatestFamily . '.%')
			);
		}

		// Filters: PHP family
		$fltPHPFamily = $this->getState('phpFamily', null, 'cmd');

		if ($fltPHPFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.php') . ' LIKE ' . $query->quote(
					'"' . $fltPHPFamily . '.%'
				)
			);
		}

		return $query;
	}
}