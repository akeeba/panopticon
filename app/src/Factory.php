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
		self::$container ??= new Container();

		return self::$container;
	}

	public static function getApplication(): Application
	{
		self::$application ??= self::getContainer()->application;

		return self::$application;
	}
}