<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Trait;

defined('AKEEBA') || die;

use Awf\Registry\Registry;

/**
 * The AkeebaBackupTooOldTrait provides a method to check if a backup record is too old based on a maximum age.
 *
 * @since  1.1.0
 */
trait AkeebaBackupTooOldTrait
{
	/**
	 * Checks if a backup record is too old based on the maximum age defined in the config.
	 *
	 * @param   object|null  $backupRecord  The backup record object to check.
	 * @param   Registry     $config        The configuration object.
	 *
	 * @return  bool Returns true if the backup record is too old, otherwise false.
	 * @since   1.1.0
	 */
	protected function isTooOldBackup(?object $backupRecord, Registry $config): bool
	{
		$maxAge = (int) $config->get('config.backup.max_age', 168);

		if ($maxAge <= 0)
		{
			return false;
		}

		if (empty($backupRecord))
		{
			return false;
		}

		if (!in_array($backupRecord?->meta ?? null, ['remote', 'ok', 'complete']))
		{
			return false;
		}

		$backupStart = $backupRecord?->backupstart ?? null;

		if (empty($backupStart))
		{
			return false;
		}

		try
		{
			$backupStart = new \DateTime($backupStart);
		}
		catch (\Exception)
		{
			return false;
		}

		$hours = floor((time() - $backupStart->getTimestamp()) / 3600);

		if ($hours < 0)
		{
			return false;
		}

		return $hours > $maxAge;
	}

}