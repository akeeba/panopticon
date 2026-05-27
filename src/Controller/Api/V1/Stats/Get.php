<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Stats;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Task\Status;

/**
 * API handler for GET /v1/stats — global dashboard counters.
 *
 * Returns lightweight aggregate counts suitable for Home Assistant sensors, status boards, and
 * similar monitoring integrations. All values are derived from aggregate SQL queries; no PHP
 * loop over individual sites is performed.
 *
 * ACL: requires panopticon.super (counters are global and cannot be meaningfully scoped).
 *
 * @since  1.6.2
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesRead);
		$this->requireSuperUser();

		$db = $this->container->db;

		// ── Sites counters ──────────────────────────────────────────────────
		// Build the query first so $query is available for jsonExtract() calls.

		$query = $db->getQuery(true);

		$query
			->select(
				[
					'COUNT(*) AS ' . $db->quoteName('total'),
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 THEN 1 ELSE 0 END) AS '
					. $db->quoteName('enabled'),

					// CMS update available (core.canUpgrade = true JSON bool)
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.canUpgrade')
					. ' = TRUE THEN 1 ELSE 0 END) AS ' . $db->quoteName('with_cms_update'),

					// Extension updates available (extensions.hasUpdates = 1)
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.extensions.hasUpdates')
					. ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('with_ext_updates'),

					// Backup OK: Akeeba Backup Pro connected AND meta is ok/complete/remote
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. 'CAST(JSON_UNQUOTE(' . $query->jsonExtract($db->quoteName('config'), '$.akeebabackup.info.api') . ') AS UNSIGNED) > 1 AND '
					. 'JSON_UNQUOTE(' . $query->jsonExtract($db->quoteName('config'), '$.akeebabackup.latest.meta') . ')'
					. " IN ('ok', 'complete', 'remote') "
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('backup_ok'),

					// Backup problem: Akeeba Backup Pro connected but no good backup
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. 'CAST(JSON_UNQUOTE(' . $query->jsonExtract($db->quoteName('config'), '$.akeebabackup.info.api') . ') AS UNSIGNED) > 1 AND '
					. '(JSON_UNQUOTE(' . $query->jsonExtract($db->quoteName('config'), '$.akeebabackup.latest.meta') . ')'
					. " NOT IN ('ok', 'complete', 'remote')"
					. ' OR ' . $query->jsonExtract($db->quoteName('config'), '$.akeebabackup.latest') . ' IS NULL)'
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('backup_problem'),

					// Core checksums OK: lastCheck is not null AND lastStatus = true
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.coreChecksums.lastCheck') . ' IS NOT NULL AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.coreChecksums.lastStatus') . ' = TRUE '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('core_checksums_ok'),

					// Core checksums fail: lastCheck is not null AND lastStatus = false
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.coreChecksums.lastCheck') . ' IS NOT NULL AND '
					. $query->jsonExtract($db->quoteName('config'), '$.core.coreChecksums.lastStatus') . ' = FALSE '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('core_checksums_fail'),
				]
			)
			->from($db->quoteName('#__sites'));

		$db->setQuery($query);
		$siteRow = $db->loadObject();

		// ── File scanner counters (via tasks table) ──────────────────────────
		// file_scanner_ok / fail are determined from the most recent completed filescanner
		// task per site. "Completed" means not currently running (exit code ≠ RUNNING/WILL_RESUME/
		// INITIAL_SCHEDULE). Sites without any filescanner task are treated as "unknown" and
		// excluded from both counts.
		$runningCodes  = implode(',', [Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]);
		$scannerSubsql = sprintf(
			'(SELECT MAX(%s) FROM %s t2 WHERE t2.%s = t.%s AND t2.%s = %s AND t2.%s = 1)',
			$db->quoteName('id'),
			$db->quoteName('#__tasks'),
			$db->quoteName('site_id'),
			$db->quoteName('site_id'),
			$db->quoteName('type'),
			$db->quote('filescanner'),
			$db->quoteName('enabled')
		);

		$scannerQuery = $db->getQuery(true);
		$scannerQuery
			->select(
				[
					'SUM(CASE WHEN t.' . $db->quoteName('last_exit_code') . ' = ' . Status::OK->value
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('scanner_ok'),
					'SUM(CASE WHEN t.' . $db->quoteName('last_exit_code') . ' NOT IN (' . $runningCodes . ', 0)'
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('scanner_fail'),
				]
			)
			->from($db->quoteName('#__tasks') . ' t')
			->join(
				'INNER',
				$db->quoteName('#__sites') . ' s ON s.' . $db->quoteName('id') . ' = t.' . $db->quoteName('site_id')
				. ' AND s.' . $db->quoteName('enabled') . ' = 1'
			)
			->where('t.' . $db->quoteName('type') . ' = ' . $db->quote('filescanner'))
			->where('t.' . $db->quoteName('enabled') . ' = 1')
			->where('t.' . $db->quoteName('id') . ' = ' . $scannerSubsql);

		$db->setQuery($scannerQuery);
		$scannerRow = $db->loadObject();

		// ── Tasks counters ──────────────────────────────────────────────────
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

					// Pending: enabled, not running, overdue (next_execution ≤ NOW)
					'SUM(CASE WHEN ' . $db->quoteName('enabled') . ' = 1 AND '
					. $db->quoteName('last_exit_code') . ' IN (' . $pendingCodes . ') AND '
					. $db->quoteName('next_execution') . ' <= UTC_TIMESTAMP() '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('pending'),

					// Running: currently executing
					'SUM(CASE WHEN ' . $db->quoteName('last_exit_code') . ' IN ('
					. Status::RUNNING->value . ',' . Status::WILL_RESUME->value . ') '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('running'),

					// Failed: last exit was an error code
					'SUM(CASE WHEN ' . $db->quoteName('last_exit_code')
					. ' NOT IN (' . $failedExclude . ') AND '
					. $db->quoteName('last_exit_code') . ' >= 0 '
					. 'THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed'),
				]
			)
			->from($db->quoteName('#__tasks'));

		$db->setQuery($tasksQuery);
		$tasksRow = $db->loadObject();

		$this->sendJsonResponse([
			'sites' => [
				'total'               => (int) ($siteRow->total ?? 0),
				'enabled'             => (int) ($siteRow->enabled ?? 0),
				'with_cms_update'     => (int) ($siteRow->with_cms_update ?? 0),
				'with_ext_updates'    => (int) ($siteRow->with_ext_updates ?? 0),
				'backup_ok'           => (int) ($siteRow->backup_ok ?? 0),
				'backup_problem'      => (int) ($siteRow->backup_problem ?? 0),
				'core_checksums_ok'   => (int) ($siteRow->core_checksums_ok ?? 0),
				'core_checksums_fail' => (int) ($siteRow->core_checksums_fail ?? 0),
				'file_scanner_ok'     => (int) ($scannerRow->scanner_ok ?? 0),
				'file_scanner_fail'   => (int) ($scannerRow->scanner_fail ?? 0),
			],
			'tasks' => [
				'total'   => (int) ($tasksRow->total ?? 0),
				'pending' => (int) ($tasksRow->pending ?? 0),
				'running' => (int) ($tasksRow->running ?? 0),
				'failed'  => (int) ($tasksRow->failed ?? 0),
			],
		]);
	}
}
