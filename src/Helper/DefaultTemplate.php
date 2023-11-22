<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;


use Akeeba\Panopticon\Factory;
use Awf\Document\Menu\Item;
use Awf\Document\Toolbar\Button;
use Awf\Input\Filter;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\ArrayHelper;
use Awf\Utils\Template;

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

		Factory::getApplication()->getDocument()->addScript(Uri::base() . 'media/js/darkmode.min.js', defer: true);
	}

	private static function isSubmenuActive(Item $item): bool
	{
		if (count($item->getChildren()) === 0)
		{
			return $item->isActive();
		}

		return array_reduce(
			$item->getChildren(),
			fn(bool $carry, Item $item) => $carry || self::isSubmenuActive($item),
			false
		);
	}

	public static function getRenderedMenuItem(Item $item, string $listItemClass = 'nav-item', $anchorClass = 'nav-link', bool $onlyChildren = false): string
	{
		// If it's the root menu item render its children without wrapping in a dropdown
		if ($onlyChildren)
		{
			return array_reduce(
				$item->getChildren(),
				fn($html, $item) => $html . self::getRenderedMenuItem($item),
				''
			);
		}

		$html        = '';
		$hasChildren = count($item->getChildren()) > 0;

		$isActiveItem = self::isSubmenuActive($item);
		$isDivider = $item->getTitle() === '---';

		$liClass = $listItemClass . ($hasChildren ? ' dropdown' : '');
		$liClass .= $isActiveItem ? ' active' : '';
		$html    .= sprintf(
			"<li class=\"%s\"%s>",
			$liClass,
			$isDivider ? ' role="presentation"' : ''
		);

		$icon = $item->getIcon();

		if (!empty($icon))
		{
			$icon = sprintf('<span class="%s me-1" aria-hidden="true"></span>', $icon);
		}

		if (!$hasChildren)
		{
            $isDisabled = str_ends_with($item->getUrl(), '#!disabled!');
			$aClass = $anchorClass . ($isActiveItem ? ' active' : '') . ($isDisabled ? ' disabled' : '');

			$aria   = $isActiveItem ? ' aria-current="page"' : '';

			if ($isDivider)
			{
				$html .= '<hr class="dropdown-divider" />';
			}
            elseif ($isDisabled)
            {
                $html .= sprintf(
                    '<span class="%s">%s%s</span>',
                    $aClass,
                    $icon,
                    $item->getTitle()
                );
            }
			else
			{
				$html .= sprintf(
					'<a class="%s"%s href="%s">%s%s</a>',
					$aClass,
					$aria,
					$item->getUrl(),
					$icon,
					$item->getTitle()
				);
			}
		}
		else
		{
			$aClass = $anchorClass . ($isActiveItem ? ' active' : '');
			$aria   = $isActiveItem ? ' aria-current="page"' : '';

			$html .= sprintf(
				'<a class="nav-link dropdown-toggle %s" href="#" role="button" data-bs-toggle="dropdown"%s>%s%s</a>',
				$aClass,
				$aria,
				$icon,
				$item->getTitle()
			);
			$html .= '<ul class="dropdown-menu dropdown-menu-end">';
			$html .= array_reduce(
				$item->getChildren(),
				fn(string $carry, Item $item) => $carry . self::getRenderedMenuItem($item, '', 'dropdown-item'),
				''
			);
			$html .= '</ul>';
		}

		$html .= '</li>';

		return $html;
	}

	public static function getRenderedToolbarButtons(): string
	{
		$document = Factory::getApplication()->getDocument();
		$buttons = $document->getToolbar()->getButtons();

		if (empty($buttons))
		{
			return '';
		}

		return array_reduce(
			$buttons,
			function (string $html, Button $button) {
				$icon = !empty($button->getIcon())
					? sprintf(
						'<span class="%s pe-2" aria-hidden="true"></span>',
						$button->getIcon(),
					)
					: '';

				if (!empty($button->getUrl()))
				{
					$html .= sprintf(
						'<a class="btn btn-sm %s" href="%s" id="%s">',
						$button->getClass(),
						$button->getUrl(),
						$button->getId(),
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
					catch (\Exception $e)
					{
						$decoded = null;
					}

					if ($decoded)
					{
						$attribs = array_merge(
							[
								'class' => 'btn btn-sm ' . $button->getClass(),
								'id' => $button->getId(),
							],
							$decoded
						);

						$html .= sprintf(
							'<button type="button" %s>%s%s</button>',
							ArrayHelper::toString($attribs),
							$icon,
							$button->getTitle()
						);
					}
					else
					{
						$html .= sprintf(
							'<button class="btn btn-sm %s" id="%s" onclick="%s">',
							$button->getClass(),
							$button->getId(),
							$button->getOnClick(),
						);
						$html .= $icon . $button->getTitle();
						$html .= '</button>';
					}

					return $html;
				}

				$html .= sprintf(
					'<button class="btn btn-sm %s" id="%s">',
					$button->getClass(),
					$button->getId(),
				);
				$html .= $icon . $button->getTitle();
				$html .= '</button>';

				return $html;
			},
			''
		);
	}

	public static function getRenderedMessages(): string
	{
		static $messageTypes = [
			'error' => 'danger',
			'warning' => 'warning',
			'success' => 'success',
			'info' => 'info',
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
				Factory::getContainer()->language
					->text('PANOPTICON_APP_LBL_MESSAGETYPE_' . $type)
			);

			foreach ($messages as $message) {
				$html .= sprintf('<div class="my-1">%s</div>', $message);
			}

			$html .= sprintf(
				'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="%s"></button>',
				Factory::getContainer()->language
					->text('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
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

		if (!@file_exists(Template::parsePath('media://css/' . $themeFile . '.min.css', app: Factory::getApplication())))
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
}
