<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;

trait TasksPausedTrait
{
	private function setTasksPausedFlag(bool $paused = false): void
	{
		$db = Factory::getContainer()->db;

		$query = $db->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->columns(
				[
					$db->quoteName('key'),
					$db->quoteName('value'),
				]
			)
			->values(
				$db->quote('tasks.paused') . ',' . $db->quote($paused ? 1 : 0)
			);

		$db->lockTable('#__akeeba_common');
		$db->setQuery($query)->execute();
		$db->unlockTables();
	}

	private function getTasksPausedFlag(): bool
	{
		$db    = Factory::getContainer()->db;
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('tasks.paused'));

		$db->lockTable('#__akeeba_common');

		$return = ($db->setQuery($query)->loadResult() ?: 0) == 1;

		$db->unlockTables();

		return $return;
	}

	private function areTasksRunning(): bool
	{
		$db = Factory::getContainer()->db;
		$db->lockTable('#__tasks');

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__tasks'))
			->where(
				[
					$db->quoteName('enabled') . ' = 1',
					$db->quoteName('last_exit_code') . ' = ' . Status::RUNNING->value,
				]
			);

		$ret = ($db->setQuery($query)->loadResult() ?: 0) != 0;

		$db->unlockTables();

		return $ret;
	}
}