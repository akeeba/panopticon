<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Helper;


use Akeeba\Panopticon\Factory;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

abstract class DefaultTemplate
{
	public static function applyFontSize(): void
	{
		$container    = Factory::getContainer();
		$user         = $container->userManager->getUser();
		$baseFontSize = $container->appConfig->get('fontsize', $user->getParameters()->get('display.base_font_size', null));

		if (!is_numeric($baseFontSize))
		{
			return;
		}

		$baseFontSize = (int) $baseFontSize;

		if ($baseFontSize < 8)
		{
			return;
		}

		$container->application->getDocument()->addStyleDeclaration("body{font-size: {$baseFontSize}pt}");
	}

	public static function getDarkMode(): DarkModeEnum
	{
		$container    = Factory::getContainer();
		$user         = $container->userManager->getUser();

		try
		{
			$userDarkMode = DarkModeEnum::from($user->getParameters()->get('display.darkmode', 0) ?: 0);
		}
		catch (\Exception $e)
		{
			$userDarkMode = DarkModeEnum::APPLICATION;
		}

		try
		{
			$appDarkMode = DarkModeEnum::from($container->appConfig->get('darkmode', 1) ?: 1);
		}
		catch (\Exception $e)
		{
			$appDarkMode = DarkModeEnum::BROWSER;
		}

		if ($userDarkMode != DarkModeEnum::APPLICATION)
		{
			return $userDarkMode;
		}

		if ($appDarkMode === DarkModeEnum::APPLICATION)
		{
			return DarkModeEnum::BROWSER;
		}

		return $appDarkMode;
	}

	public static function applyDarkModeJavaScript(): void
	{
		if (self::getDarkMode() !== DarkModeEnum::BROWSER)
		{
			return;
		}

		Factory::getApplication()->getDocument()->addScript(Uri::base() . 'media/js/darkmode.min.js', async: true);
	}
}