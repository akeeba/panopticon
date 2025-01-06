<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;


use Akeeba\Panopticon\Factory;

defined('AKEEBA') || die;

abstract class TaskUtils
{
	private static ?array $siteNames = null;

	public static function getSiteName(?int $siteId): string
	{
		if (($siteId ?? 0) === 0)
		{
			return Factory::getContainer()->language
				->text('PANOPTICON_APP_LBL_SYSTEM_TASK');
		}

		if (self::$siteNames === null)
		{
			$db    = Factory::getContainer()->db;
			$query = $db->getQuery(true)
				->select([
					$db->quoteName('id'),
					$db->quoteName('name'),
				])
				->from($db->quoteName('#__sites'));

			self::$siteNames = $db->setQuery($query)->loadAssocList('id', 'name');
		}

		return self::$siteNames[$siteId]
		       ?? Factory::getContainer()->language
			       ->text('PANOPTICON_APP_LBL_UNKNOWN_SITE');
	}

	public static function getTaskDescription(string $taskType): string
	{
		$container = Factory::getContainer();

		return $container->taskRegistry->has($taskType)
			? $container->taskRegistry->get($taskType)->getDescription()
			: Factory::getContainer()->language
				->text('PANOPTICON_APP_LBL_UNKNOWN_TASK_TYPE');
	}
}