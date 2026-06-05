<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

use Akeeba\Panopticon\Model\Site;
use Awf\Uri\Uri;
use Awf\Utils\ArrayHelper;

defined('AKEEBA') || die;

/**
 * Helpers for attaching task log files to failure notification emails.
 *
 * @since 1.3.0
 */
trait LogAttachmentTrait
{
	/**
	 * Return the absolute path to a task log file, or null if it does not exist.
	 */
	private function getLogAttachmentPath(string $logIdentifier): ?string
	{
		$path = APATH_LOG . '/' . $logIdentifier . '.log';

		return is_file($path) ? $path : null;
	}

	/**
	 * Return the absolute URL to the log viewer page for the given log file name.
	 */
	private function getLogFileUrl(string $logFileName): string
	{
		return rtrim(Uri::base(false, $this->container), '/')
		       . '/?view=log&task=read&logfile=' . urlencode($logFileName);
	}

	/**
	 * Return the list of group IDs whose members should receive the log file attachment.
	 *
	 * Checks the per-site override first, then falls back to the global setting.
	 * Returns null when no setting is configured, meaning all recipients should get the attachment.
	 *
	 * @param   Site|null  $site  The site model, or null for a system-level task.
	 *
	 * @return  int[]|null  Array of group IDs, or null (= send to all).
	 */
	private function getLogAttachmentGroups(?Site $site): ?array
	{
		if ($site !== null)
		{
			$perSiteGroups = $site->getConfig()->get('config.core_update.email_log_groups', null);

			if ($perSiteGroups !== null)
			{
				return array_values(array_filter(ArrayHelper::toInteger((array) $perSiteGroups)));
			}
		}

		$globalGroups = $this->container->appConfig->get('log_attachment_groups', null);

		if ($globalGroups !== null)
		{
			return array_values(array_filter(ArrayHelper::toInteger((array) $globalGroups)));
		}

		return null;
	}
}
