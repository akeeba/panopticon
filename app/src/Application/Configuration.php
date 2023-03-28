<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') or die;

use Awf\Application\Configuration as AWFConfiguration;
use Awf\Container\Container;

class Configuration extends AWFConfiguration
{
	use DefaultConfigurationTrait;

	public function __construct(Container $container, $data = null)
	{
		parent::__construct($container, $data);

		$this->loadArray($this->getDefaultConfiguration());
	}

	public function set($path, $value, $separator = null)
	{
		$validator = $this->getConfigurationOptionFilterCallback($path);
		$value     = $validator($value);

		return parent::set($path, $value, $separator);
	}

	/**
	 * Loads the configuration off a JSON file
	 *
	 * @param   string  $filePath  The path to the JSON file (optional)
	 *
	 * @return  void
	 */
	public function loadConfiguration($filePath = null)
	{
		$filePath ??= $this->getDefaultPath();

		// Reset the class
		$this->data = new \stdClass();

		if (!file_exists($filePath))
		{
			return;
		}

		// Try to open the file
		require_once $filePath;

		$this->loadObject(new \AConfig());
	}

	/**
	 * Save the application configuration
	 *
	 * @param   string  $filePath  The path to the JSON file (optional)
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException  When saving fails
	 */
	public function saveConfiguration($filePath = null)
	{
		$filePath ??= $this->getDefaultPath();

		$fileData = $this->toString('Php', ['class' => 'AConfig', 'closingtag' => false]);
		$fileData = "<?php defined('AKEEBA') || die;\n" . substr($fileData, 5);

		if (!($this->container->fileSystem->write($filePath, $fileData)))
		{
			throw new \RuntimeException('Can not save ' . $filePath, 500);
		}
	}

	public function getDefaultPath(): ?string
	{
		return $this->defaultPath = $this->defaultPath ?: APATH_ROOT . '/config.php';
	}
}