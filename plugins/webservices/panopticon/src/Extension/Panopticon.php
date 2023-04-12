<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\WebServices\Panopticon\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

class Panopticon extends CMSPlugin implements SubscriberInterface
{
	private const API_PREFIX = 'v1/panopticon/';

	protected $allowLegacyListeners = false;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeApiRoute' => 'registerRoutes',
		];
	}

	public function registerRoutes(Event $event): void
	{
		/** @var ApiRouter $router */
		[$router] = $event->getArguments();

		$defaults = [
			'component' => 'com_panopticon',
		];

		$routes = [];

		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'extensions',
			'extensions.displayList',
			[],
			$defaults
		);

		$router->addRoutes($routes);
	}
}