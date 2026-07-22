<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
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
 * @property int         $id                    The group's ID
 * @property string      $title                 The group's title
 * @property string      $privileges            JSON-encoded list of privileges
 * @property int|null    $api_token_limit       Per-group API token limit override; NULL uses the global default.
 * @property string|null $mcp_disallowed_tools  JSON-encoded list of MCP tool names this group is denied access to.
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

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$search = trim($this->getState('search', null) ?? '');

		if (!empty($search))
		{
			$query->where(
				$query->quoteName('title') . ' LIKE ' . $query->quote('%' . $search . '%')
			);
		}

		return $query;
	}

	/**
	 * Make sure the badge colour is always a sanitised `#rrggbb` string, or NULL.
	 *
	 * This is the choke point which guarantees no code path — CLI, API, or a future consumer which
	 * echoes the raw column — can ever read back a colour which is unsafe to put in a `style`
	 * attribute.
	 *
	 * @return  static
	 * @since   2.2.1
	 */
	public function check()
	{
		parent::check();

		$this->colour = $this->getContainer()->helper->colour->sanitise($this->colour);

		return $this;
	}

	public function getPrivileges(): array
	{
		return self::normalisePrivileges($this->privileges);
	}

	/**
	 * Coerce a raw `privileges` value into a list of privilege names.
	 *
	 * The `privileges` column does not always hold a JSON array. The default "Users" group used to be seeded with the
	 * JSON object `{}`, and `setPrivileges()` used to emit an object whenever it filtered out a privilege which was not
	 * the last one in the list, since `array_filter()` preserves keys. Decoding those rows returns an object, not an
	 * array, which used to be a fatal error in every consumer.
	 *
	 * @param   mixed  $raw  Raw column value: JSON string, decoded array or object, or NULL.
	 *
	 * @return  string[]  A (possibly empty) list of privilege names.
	 * @since   2.2.1
	 */
	public static function normalisePrivileges(mixed $raw): array
	{
		if (is_string($raw))
		{
			$raw = json_decode($raw, true);
		}

		if (is_object($raw))
		{
			$raw = get_object_vars($raw);
		}

		if (!is_array($raw))
		{
			return [];
		}

		return array_values(
			array_filter(
				array_map(fn($x) => is_scalar($x) ? (string) $x : null, $raw)
			)
		);
	}

	public function getApiTokenLimit(): ?int
	{
		return $this->api_token_limit === null ? null : (int) $this->api_token_limit;
	}

	/**
	 * Get the list of MCP tool names this group is denied access to.
	 *
	 * @return  string[]  A (possibly empty) list of tool names.
	 * @since   2.2.0
	 */
	public function getMcpDisallowedTools(): array
	{
		$raw = $this->mcp_disallowed_tools;

		if (is_array($raw))
		{
			return array_values(array_filter(array_map('strval', $raw)));
		}

		if (empty($raw))
		{
			return [];
		}

		$decoded = json_decode($raw, true);

		return is_array($decoded)
			? array_values(array_filter(array_map('strval', $decoded)))
			: [];
	}

	/**
	 * Set the list of MCP tool names this group is denied access to.
	 *
	 * An empty list is stored as NULL, meaning "this group imposes no MCP tool restriction".
	 *
	 * @param   string[]  $tools  The list of tool names to deny.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	public function setMcpDisallowedTools(array $tools): void
	{
		$tools = array_values(
			array_unique(
				array_filter(
					array_map(
						fn($name) => preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $name))),
						$tools
					)
				)
			)
		);

		$this->mcp_disallowed_tools = empty($tools) ? null : json_encode($tools);
	}

	public function setPrivileges(array $privileges): void
	{
		// array_filter() preserves keys, so array_values() has to come last, otherwise we'd store a JSON object.
		$privileges = array_values(
			array_filter(
				$privileges,
				fn($x) => in_array($x, ['panopticon.view', 'panopticon.run', 'panopticon.admin'])
			)
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

		$usedGroupIds = $this->getUsedGroupIds($forEnabledSitesOnly);

		if (empty($usedGroupIds))
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
			->where($db->quoteName('id') . ' IN(' . implode(',', $usedGroupIds) . ')');

		try
		{
			return $db->setQuery($query)->loadAssocList('id', 'title') ?: [];
		}
		catch (\Exception)
		{
			return [];
		}
	}

	/**
	 * Get the badge colour of every group currently used in sites.
	 *
	 * Returns exactly the same set of IDs as {@see getGroupMap()}, so the two arrays have
	 * identical keys and a template can safely do `$this->groupColours[$gid] ?? null`.
	 *
	 * @param   bool  $forEnabledSitesOnly  Whether to only consider enabled sites.
	 *
	 * @return  array<int, string|null>  id => sanitised colour, or NULL.
	 * @since   2.2.1
	 */
	public function getGroupColours(bool $forEnabledSitesOnly = true): array
	{
		$db = $this->getDbo();

		$usedGroupIds = $this->getUsedGroupIds($forEnabledSitesOnly);

		if (empty($usedGroupIds))
		{
			return [];
		}

		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('colour'),
				]
			)
			->from($db->quoteName('#__groups'))
			->where($db->quoteName('id') . ' IN(' . implode(',', $usedGroupIds) . ')');

		try
		{
			// DO NOT use loadAssocList('id', 'colour'); it returns the entire row when the colour is NULL.
			$rows = $db->setQuery($query)->loadAssocList('id') ?: [];
		}
		catch (\Exception)
		{
			return [];
		}

		$colourHelper = $this->getContainer()->helper->colour;

		return array_map(
			fn(array $row): ?string => $colourHelper->sanitise($row['colour'] ?? null),
			$rows
		);
	}

	/**
	 * Collect the group IDs actually referenced by sites' `$.config.groups` JSON array.
	 *
	 * @param   bool  $forEnabledSitesOnly  Whether to only consider enabled sites.
	 *
	 * @return  int[]
	 * @since   2.2.1
	 */
	private function getUsedGroupIds(bool $forEnabledSitesOnly): array
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
				catch (\JsonException)
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
				catch (\Throwable)
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

		return array_reduce(
			$rawItems,
			fn($carry, $items) => array_unique(array_merge($carry, $items)),
			[]
		);
	}
}