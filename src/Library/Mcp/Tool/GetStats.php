<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Library\Task\Status;

/**
 * MCP tool: global dashboard counters.
 *
 * Mirrors `GET /api/v1/stats` (Super User only). Returns lightweight aggregate counts across all sites and tasks.
 *
 * @since  2.2.0
 */
class GetStats extends AbstractTool
{
	public function getName(): string
	{
		return 'get_stats';
	}

	public function getDescription(): string
	{
		return 'Get global dashboard counters across all monitored sites and tasks: how many sites have CMS or '
			. 'extension updates available, backup status, core checksum status, and task counts. Super User only.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesRead;
	}

	public function isSuperUserOnly(): bool
	{
		return true;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => (object) [],
		];
	}

	public function __invoke(): array
	{
		$this->assertSuperUser();

		$db = $this->container->db;

		$query = $db->getQuery(true);
		$query
			->select(
				[
					'COUNT(*) AS ' . $db->quoteName('total'),
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('enabled'),
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.canUpgrade')
					. ' = TRUE THEN 1 ELSE 0 END) AS ' . $db->quoteName('with_cms_update'),
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.extensions.hasUpdates')
					. ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('with_ext_updates'),
				]
			)
			->from($db->quoteName('#__sites'));

		$siteRow = $db->setQuery($query)->loadObject();

		$pendingCodes  = implode(',', [Status::OK->value, Status::INITIAL_SCHEDULE->value]);
		$failedExclude = implode(',', [
			Status::OK->value,
			Status::RUNNING->value,
			Status::WILL_RESUME->value,
			Status::INITIAL_SCHEDULE->value,
			Status::NO_EXIT->value,
		]);

		$tasksQuery = $db->getQuery(true);
		$tasksQuery
			->select(
				[
					'COUNT(*) AS ' . $db->quoteName('total'),
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $db->quoteName('last_exit_code') . ' IN (' . $pendingCodes . ') AND '
					. $db->quoteName('next_execution') . ' <= UTC_TIMESTAMP() '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('pending'),
					'SUM(CASE WHEN ' . $db->quoteName('last_exit_code') . ' IN ('
					. Status::RUNNING->value . ',' . Status::WILL_RESUME->value . ') '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('running'),
					'SUM(CASE WHEN ' . $db->quoteName('last_exit_code')
					. ' NOT IN (' . $failedExclude . ') AND '
					. $db->quoteName('last_exit_code') . ' >= 0 '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed'),
				]
			)
			->from($db->quoteName('#__tasks'));

		$tasksRow = $db->setQuery($tasksQuery)->loadObject();

		return [
			'sites' => [
				'total'            => (int) ($siteRow->total ?? 0),
				'enabled'          => (int) ($siteRow->enabled ?? 0),
				'with_cms_update'  => (int) ($siteRow->with_cms_update ?? 0),
				'with_ext_updates' => (int) ($siteRow->with_ext_updates ?? 0),
			],
			'tasks' => [
				'total'   => (int) ($tasksRow->total ?? 0),
				'pending' => (int) ($tasksRow->pending ?? 0),
				'running' => (int) ($tasksRow->running ?? 0),
				'failed'  => (int) ($tasksRow->failed ?? 0),
			],
		];
	}
}
