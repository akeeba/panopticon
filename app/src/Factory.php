<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon;

defined('AKEEBA') || die;

class Factory
{
	private static Container|null $container = null;

	private static Application|null $application = null;

	public static function getContainer(): Container
	{
		self::$container ??=
			(function_exists('user_get_container') ? user_get_container() : null)
			?? new Container();

		return self::$container;
	}

	public static function getApplication(): Application
	{
		self::$application ??=
			(function_exists('user_get_application') ? user_get_application() : null)
			?? self::getContainer()->application;

		return self::$application;
	}
}