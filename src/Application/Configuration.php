<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\Trait\DefaultConfigurationTrait;
use Akeeba\Panopticon\Exception\Configuration\ReadOnlyRepository;
use Awf\Application\Configuration as AWFConfiguration;
use Awf\Container\Container;
use Dotenv\Dotenv;

class Configuration extends AWFConfiguration
{
	use DefaultConfigurationTrait;

	private bool $isReadWrite = true;

	public function __construct(Container $container, $data = null)
	{
		parent::__construct($container, $data);

		$this->loadArray($this->getDefaultConfiguration());
	}

	public function set($path, $value, $separator = null)
	{
		$path = str_replace(',', '', $path);

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
		// For containerized installations we will try to load from environment variables
		if ($this->loadFromServerEnvironment())
		{
			$this->isReadWrite = false;

			return;
		}

		// Attempt to load from .env files
		if ($this->loadDotenv())
		{
			$this->isReadWrite = false;

			return;
		}

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
		if (!$this->isReadWrite)
		{
			throw new ReadOnlyRepository();
		}

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
		return $this->defaultPath = $this->defaultPath ?: APATH_CONFIGURATION . '/config.php';
	}

	/**
	 * Is this a read/write repository?
	 *
	 * @return  bool
	 * @since   1.0.2
	 */
	public function isReadWrite(): bool
	{
		return $this->isReadWrite;
	}

	private function loadDotenv(): bool
	{
		// Try to load from .env
		$dotEnv = Dotenv::createArrayBacked(
			[
				APATH_ROOT,
				APATH_USER_CODE,
			],
			[
				'.env',
				'.env.' . ($_SERVER['PANOPTICON_ENVIRONMENT'] ?? $_ENV['PANOPTICON_ENVIRONMENT'] ?? 'production'),
			],
			false
		);

		$varsLoaded = $dotEnv->safeLoad();

		// If nothing is loaded assume there is no .env file
		if (empty($varsLoaded))
		{
			return false;
		}

		// Required variables: database connection
		$dotEnv->required('PANOPTICON_DBDRIVER');
		$dotEnv->required('PANOPTICON_DBHOST');
		$dotEnv->required('PANOPTICON_DBUSER');
		$dotEnv->required('PANOPTICON_DBPASS');
		$dotEnv->required('PANOPTICON_DBNAME');
		$dotEnv->required('PANOPTICON_PREFIX');
		$dotEnv->ifPresent('PANOPTICON_DBENCRYPTION')->isBoolean();

		// Apply .env variables into the application configuration repository
		foreach ($varsLoaded as $k => $v)
		{
			if (!str_starts_with($k, 'PANOPTICON_'))
			{
				continue;
			}

			$this->set(strtolower(substr($k, 11)), $v);
		}

		$this->set('finished_setup', true);

		return true;
	}

	/**
	 * Load the configuration from the container's environment.
	 *
	 * This only applies when Panopticon is containerised, and only when the PANOPTICON_USING_ENV environment
	 * variable is set to 1.
	 *
	 * @return  bool
	 * @since   1.2.1
	 */
	private function loadFromServerEnvironment(): bool
	{
		// For this to have any effect we must be running under Docker and PANOPTICON_USING_ENV must be set to 1.
		$usingEnv = $_ENV['PANOPTICON_USING_ENV'] ?? 0;

		if (!defined('APATH_IN_DOCKER') || !constant('APATH_IN_DOCKER') || !$usingEnv)
		{
			return false;
		}

		// Map some .env.docker keys to Panopticon configuration variables
		$map = [
			'MYSQL_DATABASE'       => 'dbname',
			'MYSQL_USER'           => 'dbuser',
			'MYSQL_PASSWORD'       => 'dbpass',
			'PANOPTICON_DB_HOST'   => 'dbhost',
			'PANOPTICON_DB_PREFIX' => 'prefix',
			'TZ'                   => 'timezone',
		];

		foreach ($map as $envKey => $configKey)
		{
			if (!isset($_ENV[$envKey]))
			{
				continue;
			}

			$this->set($configKey, $_ENV[$envKey]);
		}

		// Import all other PANOPTICON_* environment variables
		foreach ($_ENV as $key => $value)
		{
			$key = strtolower($key);

			if (!str_starts_with($key, 'panopticon_'))
			{
				continue;
			}

			// Values `true`/`false` are always cast to bool
			if (in_array(strtolower($value ?: ''), ['true', 'false']))
			{
				$value = strtolower($value ?: '') === 'true';
			}

			$this->set(substr($key, 11), $value);
		}

		return true;
	}


}