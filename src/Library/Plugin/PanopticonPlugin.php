<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Plugin;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\View\FakeView;
use Awf\Container\Container;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Event\Observable;
use Awf\Event\Observer;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

/**
 * Abstract Panopticon plugin class.
 *
 * Override the getObservableEvents method to list your event methods. Otherwise, it will use (the very slow) PHP
 * Reflection to try to register all public methods whose name starts with 'on'.
 *
 * Override your constructor to set the $name of the plugin.
 *
 * If your plugin ships with its own language files, set $loadLanguage to true before calling the parent constructor.
 *
 * @since 1.1.0
 */
abstract class PanopticonPlugin extends Observer implements ContainerAwareInterface
{
	use ContainerAwareTrait;

	protected string $name = '';

	protected bool $loadLanguage = false;

	/**
	 * Constructor for the class.
	 *
	 * @param   Observable  $subject    The observable subject.
	 * @param   Container   $container  The dependency injection container.
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	public function __construct(Observable &$subject, Container $container)
	{
		$this->setContainer($container);
		$this->name = $this->name ?: $this->getDefaultName();

		if ($this->loadLanguage)
		{
			$this->loadLanguage();
		}

		parent::__construct($subject);
	}

	/**
	 * Returns the observable events for the object.
	 *
	 * This method retrieves all public methods with names starting with "on" from
	 * the current object and returns them as an array of event names.
	 *
	 * @return  string[]  An array of observable event names.
	 * @since   1.1.0
	 */
	public function getObservableEvents()
	{
		return $this->events ??= call_user_func(
			function () {
				$events = [];

				$reflection = new ReflectionObject($this);
				$methods    = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

				foreach ($methods as $m)
				{
					if (!str_starts_with($m->name, 'on'))
					{
						continue;
					}

					$events[] = $m->name;
				}

				return $events;
			}
		);
	}

	public function getName()
	{
		return $this->name;
	}

	/**
	 * Loads the language files for the plugin.
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	protected function loadLanguage(): void
	{
		$container  = $this->getContainer();
		$pluginPath = $this->getPluginPath();
		$language   = $container->language;

		if (empty($pluginPath))
		{
			return;
		}

		$appLanguage    = $container->appConfig->get('language', 'en-GB');
		$forcedLanguage = $container->segment->get('panopticon.forced_language', null);
		$userLanguage   = $container->userManager->getUser()->getParameters()->get('language');

		$language->loadLanguage($appLanguage, $pluginPath);

		if ($userLanguage)
		{
			$language->loadLanguage($userLanguage, $pluginPath, useDefault: false);
		}

		if ($forcedLanguage)
		{
			$language->loadLanguage($forcedLanguage, $pluginPath, useDefault: false);
		}
	}

	/**
	 * Retrieves the path of the plugin.
	 *
	 * This method returns the path of the plugin by checking if a directory matching the plugin's name exists either
	 * in the `src/Plugin` folder, or in the `user_core/Plugin` folder. If no directory is found, null is returned.
	 *
	 * For better performance, override this method to return `__DIR__`
	 *
	 * @return  string|null  The path of the plugin, or null if no directory is found.
	 * @since   1.1.0
	 */
	protected function getPluginPath(): ?string
	{
		$parts     = explode('\\', static::class);
		$shortName = $parts[count($parts) - 2];
		$paths     = [
			APATH_BASE . '/src/Plugin/' . $shortName,
			APATH_USER_CODE . '/Plugin/' . $shortName,
		];

		foreach ($paths as $path)
		{
			if (is_dir($path))
			{
				return $path;
			}
		}

		return null;
	}

	/**
	 * Returns the default translation string for the plugin's name
	 *
	 * This method generates a default name by using the short name of the class
	 * wrapped with "PANOPTICON_PLG_" and "_TITLE". The class name is calculated
	 * using `ReflectionClass` to ensure consistency and avoid hard-coding.
	 *
	 * @return  string  The default name for the object.
	 * @since   1.1.0
	 */
	protected function getDefaultName(): string
	{
		return sprintf("PANOPTICON_PLG_%s_TITLE", strtoupper((new ReflectionClass($this))->getShortName()));
	}

	protected function loadTemplate(string $layout, array $extraData = []): string
	{
		$container               = clone $this->getContainer();
		$templateName            = $this->getContainer()->application->getTemplate();
		$pluginName              = $this->getName();
		$container['mvc_config'] = [
			'template_path' => [
				APATH_PLUGIN . DIRECTORY_SEPARATOR . $pluginName . DIRECTORY_SEPARATOR . 'ViewTemplates',
				APATH_USER_CODE . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $pluginName
				. DIRECTORY_SEPARATOR . 'ViewTemplates',
				APATH_THEMES . DIRECTORY_SEPARATOR . $templateName . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR
				. 'Plugin' . DIRECTORY_SEPARATOR . $pluginName,
			],
		];

		return (new FakeView(
			$container,
			[
				'name' => 'Plugin' . ucfirst((string) $pluginName),
			]
		))->loadAnyTemplate($layout, $extraData);
	}
}