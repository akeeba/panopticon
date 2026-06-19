<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\Contracts\McpToolInterface;
use Akeeba\Panopticon\Library\Mcp\Tool\CancelCmsUpdate;
use Akeeba\Panopticon\Library\Mcp\Tool\CancelExtensionUpdate;
use Akeeba\Panopticon\Library\Mcp\Tool\GetSelfUpdateInfo;
use Akeeba\Panopticon\Library\Mcp\Tool\GetSite;
use Akeeba\Panopticon\Library\Mcp\Tool\GetSiteStatus;
use Akeeba\Panopticon\Library\Mcp\Tool\GetStats;
use Akeeba\Panopticon\Library\Mcp\Tool\GetSysconfig;
use Akeeba\Panopticon\Library\Mcp\Tool\GetTask;
use Akeeba\Panopticon\Library\Mcp\Tool\ListSiteExtensions;
use Akeeba\Panopticon\Library\Mcp\Tool\ListSites;
use Akeeba\Panopticon\Library\Mcp\Tool\ListTasks;
use Akeeba\Panopticon\Library\Mcp\Tool\RefreshSite;
use Akeeba\Panopticon\Library\Mcp\Tool\ScheduleCmsUpdate;
use Akeeba\Panopticon\Library\Mcp\Tool\ScheduleExtensionUpdate;

/**
 * Discovers and access-filters the MCP tools available to the current request.
 *
 * The returned set of tools mirrors the API endpoints, gated by exactly the same controls a user faces through the
 * API plus two MCP-specific layers:
 *
 *  1. **Token scope** — a tool is only exposed when the authenticating API token grants the tool's required scope
 *     (a token with no explicit scopes is treated as granting all scopes, matching the API).
 *  2. **Super User restriction** — Super-User-only tools are hidden from everyone else.
 *  3. **Global application configuration** — tools named in the `mcp_disallowed_tools` option are never exposed.
 *  4. **Per-user-group restriction** — a Super User may deny specific tools per user group.
 *
 * ### Per-group access rule: ALLOW wins
 *
 * Each user group may carry a list of *disallowed* tools. When a user belongs to several groups, a tool is denied
 * **only if every one of the user's groups denies it**. In other words, being granted a tool by *any* group overrides
 * being denied it by another group.
 *
 * This is the opposite of Joomla's "deny wins" ACL and is a deliberate "convention over configuration" choice: it
 * keeps tools available unless they are universally restricted. The global `mcp_disallowed_tools` option is the
 * absolute kill-switch that always wins.
 *
 * @since  2.2.0
 */
class ToolRegistry
{
	/**
	 * The full list of MCP tool classes, in display order.
	 *
	 * @var   class-string<McpToolInterface>[]
	 * @since 2.2.0
	 */
	private const TOOL_CLASSES = [
		// Read-only
		ListSites::class,
		GetSite::class,
		GetSiteStatus::class,
		ListSiteExtensions::class,
		ListTasks::class,
		GetTask::class,
		GetStats::class,
		GetSysconfig::class,
		GetSelfUpdateInfo::class,
		// Actions
		RefreshSite::class,
		ScheduleCmsUpdate::class,
		CancelCmsUpdate::class,
		ScheduleExtensionUpdate::class,
		CancelExtensionUpdate::class,
	];

	public function __construct(private readonly Container $container) {}

	/**
	 * Instantiate every known tool, regardless of access control.
	 *
	 * @return  array<class-string, McpToolInterface>  Map of class name to tool instance.
	 * @since   2.2.0
	 */
	public function getAllTools(): array
	{
		$result = [];

		foreach (self::TOOL_CLASSES as $class)
		{
			$result[$class] = new $class($this->container);
		}

		return $result;
	}

	/**
	 * Return the tools available to the current user, token, and configuration.
	 *
	 * @return  array<class-string, McpToolInterface>  Map of class name to tool instance.
	 * @since   2.2.0
	 */
	public function getAvailableTools(): array
	{
		$globallyDisallowed = $this->getGloballyDisallowedTools();
		$groupDisallowed    = $this->getGroupDisallowedTools();
		$allowedScopes      = ApiScope::fromJson($this->container->apiCurrentToken->scopes ?? null);
		$isSuperUser        = $this->container->userManager->getUser()->getPrivilege('panopticon.super');

		$result = [];

		foreach ($this->getAllTools() as $class => $tool)
		{
			$name = $tool->getName();

			// 1. Global application kill-switch (always wins).
			if (in_array($name, $globallyDisallowed, true))
			{
				continue;
			}

			// 2. Super-User-only tools.
			if ($tool->isSuperUserOnly() && !$isSuperUser)
			{
				continue;
			}

			// 3. Token scope (NULL allowedScopes means "all scopes", matching the API).
			$requiredScope = $tool->getRequiredScope();

			if ($requiredScope !== null && $allowedScopes !== null && !in_array($requiredScope, $allowedScopes, true))
			{
				continue;
			}

			// 4. Per-group restriction (ALLOW wins).
			if (in_array($name, $groupDisallowed, true))
			{
				continue;
			}

			$result[$class] = $tool;
		}

		return $result;
	}

	/**
	 * The list of tool names disallowed globally through the application configuration.
	 *
	 * @return  string[]
	 * @since   2.2.0
	 */
	private function getGloballyDisallowedTools(): array
	{
		$raw = (string) $this->container->appConfig->get('mcp_disallowed_tools', '');

		return array_values(
			array_filter(
				array_map('trim', explode(',', strtolower($raw)))
			)
		);
	}

	/**
	 * The list of tool names disallowed for the current user by the ALLOW-wins union of their user groups.
	 *
	 * A tool name appears in this list only when **every** group the user belongs to disallows it. Users who belong
	 * to no groups have no group-imposed restrictions.
	 *
	 * @return  string[]
	 * @since   2.2.0
	 */
	private function getGroupDisallowedTools(): array
	{
		$user     = $this->container->userManager->getUser();
		$groupIDs = $user->getParameters()->get('usergroups', []) ?: [];
		$groupIDs = array_values(array_filter(array_map('intval', (array) $groupIDs)));

		if (empty($groupIDs))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->quoteName('mcp_disallowed_tools'))
			->from($db->quoteName('#__groups'))
			->where($db->quoteName('id') . ' IN(' . implode(',', $groupIDs) . ')');

		try
		{
			$rows = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (\Throwable)
		{
			return [];
		}

		// One disallowed-set per group the user belongs to (missing/NULL config = empty set = "allows everything").
		$perGroupSets = [];

		foreach ($rows as $json)
		{
			$decoded = empty($json) ? [] : (json_decode($json, true) ?: []);
			$perGroupSets[] = is_array($decoded)
				? array_map('strval', $decoded)
				: [];
		}

		// A tool is effectively disallowed only when it appears in EVERY group's disallowed set (intersection).
		if (empty($perGroupSets))
		{
			return [];
		}

		$intersection = array_shift($perGroupSets);

		foreach ($perGroupSets as $set)
		{
			$intersection = array_intersect($intersection, $set);

			if (empty($intersection))
			{
				break;
			}
		}

		return array_values($intersection);
	}
}
