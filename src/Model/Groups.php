<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Container\Container;
use Awf\Mvc\DataModel;
use Awf\Utils\ArrayHelper;

/**
 * Handle user groups
 *
 * @property int    $id         The group's ID
 * @property string $title      The group's title
 * @property string $privileges JSON-encoded list of privileges
 */
class Groups extends DataModel
{
	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__groups';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function getPrivileges(): array
	{
		return is_array($this->privileges)
			? $this->privileges
			: (json_decode($this->privileges ?: '[]') ?: []);
	}

	public function setPrivileges(array $privileges): void
	{
		$privileges = array_values($privileges);
		$privileges = array_filter(
			$privileges, fn($x) => in_array($x, ['panopticon.view', 'panopticon.run', 'panopticon.admin'])
		);

		$this->privileges = json_encode($privileges);
	}

	/**
	 * Get the groups currently used in sites.
	 *
	 * @return  array
	 * @since   1.0.5
	 */
	public function getGroupMap(bool $forEnabledSitesOnly = true): array
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true);
		$query
			->select(
				$query->jsonExtract($db->quoteName('config'), '$.config.groups')
			)
			->from($db->quoteName('#__sites'));

		if ($forEnabledSitesOnly) {
			$query->where($db->quoteName('enabled') . ' = 1');
		}

		$query->where(
			$query->jsonExtract($db->quoteName('config'), '$.config.groups[0]') . ' IS NOT NULL'
		);

		$rawItems = $db->setQuery($query)->loadColumn() ?: [];

		if (empty($rawItems))
		{
			return [];
		}

		$rawItems = array_map(
			function ($json): ?array {
				try
				{
					$ret = json_decode($json, flags: JSON_THROW_ON_ERROR);
				}
				catch (\JsonException $e)
				{
					return null;
				}

				if (!is_array($ret))
				{
					return null;
				}

				try
				{
					$ret = ArrayHelper::toInteger($ret);
				}
				catch (\Throwable $e)
				{
					return null;
				}

				$ret = array_filter($ret);

				return empty($ret) ? null : $ret;
			},
			$rawItems
		);

		$rawItems = array_filter($rawItems);

		if (empty($rawItems))
		{
			return [];
		}

		$rawItems = array_reduce(
			$rawItems,
			fn($carry, $items) => array_unique(array_merge($carry, $items)),
			[]
		);

		if (empty($rawItems))
		{
			return [];
		}

		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('title'),
				]
			)
			->from($db->quoteName('#__groups'))
			->where($db->quoteName('id') . ' IN(' . implode(',', $rawItems) . ')');

		try
		{
			return $db->setQuery($query)->loadAssocList('id', 'title') ?: [];
		}
		catch (\Exception $e)
		{
			return [];
		}
	}
}