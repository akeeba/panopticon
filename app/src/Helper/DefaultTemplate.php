<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Helper;


use Akeeba\Panopticon\Factory;
use Awf\Document\Menu\Item;
use Awf\Document\Toolbar\Button;
use Awf\Inflector\Inflector;
use Awf\Text\Text;
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

		Factory::getApplication()->getDocument()->addScript(Uri::base() . 'media/js/darkmode.min.js', async: true);
	}

	public static function getRenderedMenuItem(Item $item, string $listItemClass = 'nav-link', $anchorClass = 'nav-link'): string
	{
		// If it's the root menu item render its children without wrapping in a dropdown
		if (empty($item->getParent()))
		{
			return array_reduce(
				$item->getChildren(),
				fn($html, $item) => $html . self::getRenderedMenuItem($item),
				''
			);
		}

		$html        = '';
		$hasChildren = count($item->getChildren()) > 0;

		$liClass = $listItemClass . ($hasChildren ? ' dropdown' : '');
		$liClass .= $item->isActive() ? ' active' : '';
		$html    .= sprintf("<li class=\"%s\">", $liClass);

		if (!$hasChildren)
		{
			$aClass = $anchorClass . ($item->isActive() ? ' active' : '');
			$aria   = $item->isActive() ? ' aria-current="page"' : '';

			$html .= sprintf(
				'<a class="%s%s" href="%s">%s</a>',
				$aClass,
				$aria,
				$item->getUrl(),
				$item->getTitle()
			);
		}
		else
		{
			$html .= sprintf(
				'<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">%s</a>',
				$item->getTitle()
			);
			$html .= '<ul class="dropdown-menu">';
			$html .= array_reduce(
				$item->getChildren(),
				fn(string $carry, Item $item) => $carry . self::getRenderedMenuItem($item, '', 'dropdown-item')
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
						'<span class="%s" aria-hidden="true"></span>',
						$button->getIcon(),
					)
					: '';

				if ($button->getUrl())
				{
					$html .= sprintf(
						'<a class="btn btn-sm %s" id="%s">',
						$button->getClass(),
						$button->getId(),
					);
					$html .= $icon . $button->getTitle();
					$html .= '</a>';

					return $html;
				}

				$html .= sprintf(
					'<button class="btn btn-sm %s" id="%s">',
					$button->getClass(),
					$button->getId(),
				);
				$html .= $icon . $button->getTitle();
				$html .= '</a>';

				return $html;
			},
			''
		);
	}

	public static function getRenderedMessages(): string
	{
		static $messageTypes = [
			'error' => 'failure',
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

			$html .= sprintf('<div class="alert alert-%s">', $class);
			$html .= sprintf('<h3 class="alert-heading visually-hidden alert-dismissible fade show">%s</h3>', Text::_('PANOPTICON_APP_LBL_MESSAGETYPE_' . $type));

			foreach ($messages as $message) {
				$html .= sprintf('<div class="my-1">%s</div>', $message);
			}

			$html .= sprintf(
				'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="%s"></button>',
				Text::_('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
			);

			$html .= '</div>';
		}

		return $html;
	}
}