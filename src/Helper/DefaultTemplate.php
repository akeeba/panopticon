<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;


use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Enumerations\DarkModeEnum;
use Akeeba\Panopticon\Library\Toolbar\DropdownButton;
use Awf\Document\Menu\Item;
use Awf\Document\Toolbar\Button;
use Awf\Input\Filter;
use Awf\Uri\Uri;
use Awf\Utils\ArrayHelper;
use Awf\Utils\Template;
use Exception;

defined('AKEEBA') || die;

abstract class DefaultTemplate
{
	private static array $importMap = [];

	public static function applyFontSize(): void
	{
		$container    = Factory::getContainer();
		$user         = $container->userManager->getUser();
		$baseFontSize = $container->appConfig->get(
			'fontsize', $user->getParameters()->get('display.base_font_size', null)
		);

		if (!is_numeric($baseFontSize))
		{
			return;
		}

		$baseFontSize = (int) $baseFontSize;

		if ($baseFontSize < 8)
		{
			return;
		}

		$container->application->getDocument()->addStyleDeclaration("html{font-size: {$baseFontSize}pt}");
	}

	public static function getDarkMode(): DarkModeEnum
	{
		$container = Factory::getContainer();
		$user      = $container->userManager->getUser();

		try
		{
			$userDarkMode = DarkModeEnum::from($user->getParameters()->get('display.darkmode', 0) ?: 0);
		}
		catch (Exception)
		{
			$userDarkMode = DarkModeEnum::APPLICATION;
		}

		try
		{
			$appDarkMode = DarkModeEnum::from($container->appConfig->get('darkmode', 1) ?: 1);
		}
		catch (Exception)
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

		Factory::getApplication()->getDocument()->addScript(Uri::base() . 'media/js/darkmode.min.js', defer: true);
	}

	public static function getRenderedMenuItem(
		Item $item, string $listItemClass = 'nav-item', $anchorClass = 'nav-link', bool $onlyChildren = false
	): string
	{
		// If it's the root menu item render its children without wrapping in a dropdown
		if ($onlyChildren)
		{
			return array_reduce(
				$item->getChildren(), fn($html, $item) => $html . self::getRenderedMenuItem($item), ''
			);
		}

		$html        = '';
		$hasChildren = count($item->getChildren()) > 0;

		$isActiveItem = self::isSubmenuActive($item);
		$isDivider    = $item->getTitle() === '---';

		$liClass = $listItemClass . ($hasChildren ? ' dropdown' : '');
		$liClass .= $isActiveItem ? ' active' : '';
		$html    .= sprintf(
			"<li class=\"%s\"%s>", $liClass, $isDivider ? ' role="presentation"' : ''
		);

		$icon = $item->getIcon();

		if (!empty($icon))
		{
			if (str_starts_with($icon, 'fa'))
			{
				$icon = sprintf('<span class="%s me-1" aria-hidden="true"></span>', $icon);
			}
		}

		if (!$hasChildren)
		{
			$url           = $item->getUrl();
			$isDisabled    = str_ends_with($url, '#!disabled!');
			$isHiddenTitle = str_ends_with($url, '#!hiddenTitle!');
			$aClass        = $anchorClass . ($isActiveItem ? ' active' : '') . ($isDisabled ? ' disabled' : '');
			$aria          = $isActiveItem ? ' aria-current="page"' : '';
			$title         = $item->getTitle();

			if ($isHiddenTitle)
			{
				$url = str_replace('#!hiddenTitle!', '', $url);
				$title = sprintf('<span class="visually-hidden">%s</span>', $title);
			}

			$url = $url ?: null;

			if ($isDivider)
			{
				$html .= '<hr class="dropdown-divider">';
			}
			elseif ($isDisabled)
			{
				$html .= sprintf(
					'<span class="%s">%s%s</span>', $aClass, $icon, $title
				);
			}
			elseif($url === null)
			{
				$html .= sprintf(
            		'<span class="%s">%s%s</span>',
					$aClass, $icon, $title
            	);
			}
			else
			{
				$html .= sprintf(
					'<a class="%s"%s href="%s">%s%s</a>', $aClass, $aria, $url, $icon, $title
				);
			}
		}
		else
		{
			$aClass        = $anchorClass . ($isActiveItem ? ' active' : '');
			$aria          = $isActiveItem ? ' aria-current="page"' : '';
			$title         = $item->getTitle();
			$isHiddenTitle = str_ends_with($item->getUrl(), '#!hiddenTitle!');

			if ($isHiddenTitle)
			{
				$title = sprintf('<span class="visually-hidden">%s</span>', $title);
			}

			$html .= sprintf(
				'<a class="nav-link dropdown-toggle %s" href="#" role="button" data-bs-toggle="dropdown"%s>%s%s</a>',
				$aClass, $aria, $icon, $title
			);
			$html .= '<ul class="dropdown-menu dropdown-menu-end">';
			$html .= array_reduce(
				$item->getChildren(),
				fn(string $carry, Item $item) => $carry . self::getRenderedMenuItem($item, '', 'dropdown-item'), ''
			);
			$html .= '</ul>';
		}

		$html .= '</li>';

		return $html;
	}

	public static function getRenderedToolbarButtons(?array $buttons = null): string
	{
		$document = Factory::getApplication()->getDocument();
		$buttons  ??= $document->getToolbar()->getButtons();

		if (empty($buttons))
		{
			return '';
		}

		return array_reduce(
			$buttons, function (string $html, Button $button) {
			$icon = !empty($button->getIcon()) ? sprintf(
				'<span class="%s pe-2" aria-hidden="true"></span>', $button->getIcon(),
			) : '';

			if (!empty($button->getUrl()))
			{
				$classes = array_filter(explode(' ', $button->getClass()));
				$target = in_array('target-blank', $classes) ? 'target="_blank"' : '';
				$html .= sprintf(
					'<a class="btn btn-sm %s" href="%s" id="%s" %s>',
					$button->getClass(),
					$button->getUrl(),
					$button->getId(),
					$target
				);
				$html .= $icon . $button->getTitle();
				$html .= '</a>';

				return $html;
			}
			elseif (!empty($button->getOnClick()))
			{
				try
				{
					$decoded = @json_decode($button->getOnClick(), true);
				}
				catch (Exception)
				{
					$decoded = null;
				}

				if ($decoded)
				{
					$attribs = array_merge(
						[
							'class' => 'btn btn-sm ' . $button->getClass(),
							'id'    => $button->getId(),
						], $decoded
					);

					$html .= sprintf(
						'<button type="button" %s>%s%s</button>', ArrayHelper::toString($attribs), $icon,
						$button->getTitle()
					);
				}
				else
				{
					$html .= sprintf(
						'<button class="btn btn-sm %s" id="%s" onclick="%s">', $button->getClass(), $button->getId(),
						$button->getOnClick(),
					);
					$html .= $icon . $button->getTitle();
					$html .= '</button>';
				}

				return $html;
			}

			// Get the additional attributes, used in drop-down buttons
			$additionalAttributes = [];

			if ($button instanceof DropdownButton)
			{
				$additionalAttributes = [
					'data-bs-toggle' => 'dropdown',
					'aria-expanded'  => 'false',
					'role'           => 'button',
				];
			}

			// Get the CSS classes of the button
			$classes = array_filter(explode(' ', $button->getClass()));

			// Drop-down buttons always need the dropdown-toggle CSS class
			if ($button instanceof DropdownButton && !in_array('dropdown-toggle', $classes))
			{
				$classes[] = 'dropdown-toggle';
			}

			if ($button instanceof DropdownButton)
			{
				$html .= '<div class="dropdown">';
			}

			$html .= sprintf(
				'<button class="btn btn-sm %s" id="%s"%s>', implode(' ', $classes), $button->getId(),
				ArrayHelper::toString($additionalAttributes)
			);
			$html .= $icon . $button->getTitle();
			$html .= '</button>';

			if ($button instanceof DropdownButton)
			{
				$html .= self::getRenderedDropdownButtonMenu($button->getButtons());
				$html .= '</div>';
			}

			return $html;
		}, ''
		);
	}

	public static function getRenderedMessages(): string
	{
		static $messageTypes = [
			'error'   => 'danger',
			'warning' => 'warning',
			'success' => 'success',
			'info'    => 'info',
		];

		$html = '';

		foreach ($messageTypes as $type => $class)
		{
			$messages = Factory::getApplication()->getMessageQueueFor($type);

			if (empty($messages))
			{
				continue;
			}

			$html .= sprintf('<div class="alert alert-%s alert-dismissible fade show">', $class);
			$html .= sprintf(
				'<h3 class="alert-heading visually-hidden">%s</h3>',
				Factory::getContainer()->language->text('PANOPTICON_APP_LBL_MESSAGETYPE_' . $type)
			);

			foreach ($messages as $message)
			{
				$html .= sprintf('<div class="my-1">%s</div>', $message);
			}

			$html .= sprintf(
				'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="%s"></button>',
				Factory::getContainer()->language->text('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
			);


			$html .= '</div>';
		}

		Factory::getApplication()->clearMessageQueue();

		return $html;
	}

	public static function getThemeColour(): string
	{
		$themeFile = Factory::getContainer()->appConfig->get('theme', 'theme') ?: 'theme';
		$themeFile = (new Filter())->clean($themeFile, 'path');

		if (!@file_exists(
			Template::parsePath('media://css/' . $themeFile . '.min.css', app: Factory::getApplication())
		))
		{
			$themeFile = 'theme';
		}

		$currentTemplate = Factory::getApplication()->getTemplate();

		$filePaths = [
			APATH_THEMES . '/' . $currentTemplate . '/media/css/' . $themeFile . '.min.css',
			APATH_THEMES . '/' . $currentTemplate . '/media/css/' . $themeFile . '.css',
			APATH_MEDIA . '/css/' . $themeFile . '.min.css',
			APATH_MEDIA . '/css/' . $themeFile . '.css',
		];

		foreach ($filePaths as $filePath)
		{
			if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath))
			{
				continue;
			}

			$contents = @file_get_contents($filePath);

			if ($contents === false)
			{
				continue;
			}

			if (!preg_match("%--bs-primary:\s*#([0-9a-f]{3,6})\s*;%", $contents, $matches))
			{
				continue;
			}

			return '#' . $matches[1];
		}

		return '';
	}

	/**
	 * Render a Bootstrap drop-down given an array of Button objects
	 *
	 * @param   array  $buttons  The array of button object to render
	 *
	 * @return  string
	 * @since   1.0.5
	 */
	public static function getRenderedDropdownButtonMenu(array $buttons): string
	{
		$buttons = array_filter($buttons, fn($x) => $x instanceof Button);

		if (empty($buttons))
		{
			return '';
		}

		$html = '<ul class="dropdown-menu">';

		/** @var Button $button */
		foreach ($buttons as $button)
		{
			$icon = !empty($button->getIcon()) ? sprintf(
				'<span class="%s pe-2" aria-hidden="true"></span>', $button->getIcon(),
			) : '';

			$classes   = explode(' ', $button->getClass());
			$isHeader  = false;
			$isDivider = $button->getTitle() === '---';

			if (in_array('header', $classes))
			{
				$isHeader = true;
				$classes  = array_filter($classes, fn($x) => $x !== 'header');
			}

			if (in_array('divider', $classes))
			{
				$isDivider = true;
				$classes   = array_filter($classes, fn($x) => $x !== 'divider');
			}

			$html .= '<li>';

			if ($isHeader)
			{
				$html .= sprintf(
					'<h6 class="dropdown-header %s">%s</h6>', implode(' ', $classes), $icon . $button->getTitle()
				);
			}
			elseif ($isDivider)
			{
				$html .= '<hr class="dropdown-divider">';
			}
			elseif (!empty($button->getUrl()))
			{
				$html .= sprintf(
					'<a class="dropdown-item %s" href="%s" id="%s">', implode(' ', $classes), $button->getUrl(),
					$button->getId(),
				);
				$html .= $icon . $button->getTitle();
				$html .= '</a>';
			}
			elseif (!empty($button->getOnClick()))
			{
				try
				{
					$decoded = @json_decode($button->getOnClick(), true);
				}
				catch (Exception)
				{
					$decoded = null;
				}

				if ($decoded)
				{
					$attribs = array_merge(
						[
							'class' => 'dropdown-item ' . implode(' ', $classes),
							'id'    => $button->getId(),
						], $decoded
					);

					$html .= sprintf(
						'<a %s>%s%s</a>', ArrayHelper::toString($attribs), $icon, $button->getTitle()
					);
				}
				else
				{
					$html .= sprintf(
						'<a class="dropdown-item %s" id="%s" onclick="%s">', implode(' ', $classes), $button->getId(),
						$button->getOnClick(),
					);
					$html .= $icon . $button->getTitle();
					$html .= '</a>';
				}
			}
			else
			{
				$html .= sprintf(
					'<a class="dropdown-item %s" id="%s" href="#">%s%s</a>', implode(' ', $classes), $button->getId(),
					$icon, $button->getTitle()
				);

				if ($button instanceof DropdownButton)
				{
					$html .= self::getRenderedDropdownButtonMenu($button->getButtons());
				}
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	public static function addImportMapEntry(string $key, string $url): void
	{
		self::$importMap[$key] = $url;
	}

	public static function removeImportMapEntry(string $key): void
	{
		if (!isset(self::$importMap[$key]))
		{
			return;
		}

		unset(self::$importMap[$key]);
	}

	public static function getImportMapAsJson(): ?string
	{
		return empty(self::$importMap)
			? null
			: json_encode(
				[
					'imports' => self::$importMap,
				], JSON_PRETTY_PRINT
			);
	}

	private static function isSubmenuActive(Item $item): bool
	{
		if (count($item->getChildren()) === 0)
		{
			return $item->isActive();
		}

		return array_reduce(
			$item->getChildren(), fn(bool $carry, Item $item) => $carry || self::isSubmenuActive($item), false
		);
	}
}
