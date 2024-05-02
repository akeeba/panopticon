<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * =====================================================================================================================
 * Local Development Helper
 * =====================================================================================================================
 *
 * To use this, create the file user_code/early_bootstrap.php with these contents:
 *
 * require_once __DIR__ . '/../assets/localdev-early_bootstrap.php';
 *
 * To customise its behaviour define the respective control constants at the top of the user_code/bootstrap.php file,
 * before requiring this file here.
 *
 * =====================================================================================================================
 */

// Control constants. Can be overridden in user_code/bootstrap.php
defined('LOCAL_DEVELOPMENT_LOAD_AWF') || define('LOCAL_DEVELOPMENT_LOAD_AWF', true);
defined('LOCAL_DEVELOPMENT_LOAD_JSON_API') || define('LOCAL_DEVELOPMENT_LOAD_JSON_API', true);
defined('LOCAL_DEVELOPMENT_LOAD_STATS_COLLECTOR') || define('LOCAL_DEVELOPMENT_LOAD_STATS_COLLECTOR', true);
defined('LOCAL_DEVELOPMENT_UPDATES_URL') || define('LOCAL_DEVELOPMENT_UPDATES_URL', '');
defined('LOCAL_DEVELOPMENT_STATS_URL') || define('LOCAL_DEVELOPMENT_STATS_URL', '');

if (PHP_OS === 'WINNT')
{
	define('LOCAL_DEVELOPMENT_PROJECTS_BASE', 'D:\\Projects');
}

// Load our local development helper classes
require_once __DIR__ . '/LocalDev/LocalLibraryLoader.php';

// Load the development version of AWF
(new class extends \Akeeba\Panopticon\LocalDev\LocalLibraryLoader {
	public function __construct()
	{
		$localDevelopmentProjectsBase = defined('LOCAL_DEVELOPMENT_PROJECTS_BASE')
			? LOCAL_DEVELOPMENT_PROJECTS_BASE
			: ($_SERVER['HOME'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH']) . '/Projects';

		parent::__construct(
			composerAutoloaderFile: $localDevelopmentProjectsBase . '/akeeba/awf/vendor/autoload.php',
			namespace: 'Awf',
			constantName: 'LOCAL_DEVELOPMENT_LOAD_AWF',
		);
	}
})();

// Load the development version of the JSON Backup API library
(new class extends \Akeeba\Panopticon\LocalDev\LocalLibraryLoader {
	public function __construct()
	{
		$localDevelopmentProjectsBase = defined('LOCAL_DEVELOPMENT_PROJECTS_BASE')
			? LOCAL_DEVELOPMENT_PROJECTS_BASE
			: ($_SERVER['HOME'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH']) . '/Projects';

		parent::__construct(
			composerAutoloaderFile: $localDevelopmentProjectsBase . '/akeeba/json-backup-api/vendor/autoload.php',
			namespace: 'Akeeba\BackupJsonApi',
			constantName: 'LOCAL_DEVELOPMENT_LOAD_JSON_API',
		);
	}
})();

// Load the development version of the Stats Collector library
(new class extends \Akeeba\Panopticon\LocalDev\LocalLibraryLoader {
	public function __construct()
	{
		$localDevelopmentProjectsBase = defined('LOCAL_DEVELOPMENT_PROJECTS_BASE')
			? LOCAL_DEVELOPMENT_PROJECTS_BASE
			: ($_SERVER['HOME'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH']) . '/Projects';

		parent::__construct(
			composerAutoloaderFile: $localDevelopmentProjectsBase . '/akeeba/stats_collector/vendor/autoload.php',
			namespace: 'Akeeba\UsageStats\Collector',
			constantName: 'LOCAL_DEVELOPMENT_LOAD_STATS_COLLECTOR',
		);
	}
})();
