<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon;

use Awf\Text\Text;

defined('AKEEBA') || die;

class Factory
{
	private static Container|null $container = null;

	private static Application|null $application = null;

	public static function getContainer(): Container
	{
		$firstRun = empty(self::$container);

		self::$container ??=
			(function_exists('user_get_container') ? \user_get_container() : null)
			?? new Container();

		if ($firstRun)
		{
			Text::setContainer(self::$container);
		}

		return self::$container;
	}

	public static function getApplication(): Application
	{
		self::$application ??=
			(function_exists('user_get_application') ? \user_get_application() : null)
			?? self::getContainer()->application;

		return self::$application;
	}
}