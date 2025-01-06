<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Plugin;

use Akeeba\Panopticon\Factory;

defined('AKEEBA') || die;

final class PluginHelper
{
	private static bool $loadedPlugins = false;

	public static function loadPlugins(): void
	{
		if (self::$loadedPlugins)
		{
			return;
		}

		self::$loadedPlugins = true;

		$container       = Factory::getContainer();
		$eventDispatcher = $container->eventDispatcher;
		$processed       = [];

		foreach (self::detectPlugins() as $className)
		{
			if (in_array($className, $processed) || !class_exists($className, true) || !in_array(PanopticonPlugin::class, class_parents($className)))
			{
				continue;
			}

			$processed[] = $className;

			$o = new $className($eventDispatcher, $container);
		}
	}

	private static function detectPlugins()
	{
		$plugins = [];

		foreach (
			[
				APATH_BASE . '/src/Plugin',
				APATH_USER_CODE . '/Plugin',
			] as $path
		)
		{
			$quickFile = $path . '/plugins.php';

			if (file_exists($quickFile))
			{
				$plugins = array_merge($plugins, include $quickFile);

				continue;
			}

			try
			{
				$di = new \DirectoryIterator($path);
			}
			catch (\Exception $e)
			{
				continue;
			}

			/** @var \DirectoryIterator $folder */
			foreach ($di as $folder)
			{
				if (!$folder->isDir() || $folder->isDot())
				{
					continue;
				}

				$plugins[] = '\\Akeeba\\Panopticon\\Plugin\\' . $folder->getFilename() . '\\Plugin';
			}
		}

		return array_unique($plugins);
	}

}