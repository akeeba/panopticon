<?php
// Control constants. Can be overridden in user_code/bootstrap.php
defined('LOCAL_DEVELOPMENT_LOAD_AWF') || define('LOCAL_DEVELOPMENT_LOAD_AWF', true);
defined('LOCAL_DEVELOPMENT_LOAD_JSON_API') || define('LOCAL_DEVELOPMENT_LOAD_JSON_API', true);
defined('LOCAL_DEVELOPMENT_UPDATES_URL') || define('LOCAL_DEVELOPMENT_UPDATES_URL', '');

// Load the development version of AWF
(new class extends \Akeeba\Panopticon\LocalDev\LocalLibraryLoader {
	public function __construct()
	{
		parent::__construct(
			composerAutoloaderFile: $_SERVER['HOME'] . '/Projects/akeeba/awf/vendor/autoload.php',
			namespace: 'Awf',
			constantName: 'LOCAL_DEVELOPMENT_LOAD_AWF',
		);
	}
})();

// Load the development version of the JSON Backup API library
(new class extends \Akeeba\Panopticon\LocalDev\LocalLibraryLoader {
	public function __construct()
	{
		parent::__construct(
			composerAutoloaderFile: $_SERVER['HOME'] . '/Projects/akeeba/json-backup-api/vendor/autoload.php',
			namespace: 'Akeeba\BackupJsonApi',
			constantName: 'LOCAL_DEVELOPMENT_LOAD_JSON_API',
		);
	}
})();

// Override the update stream URL
call_user_func(function () {
	if (!defined('LOCAL_DEVELOPMENT_UPDATES_URL') || empty(LOCAL_DEVELOPMENT_UPDATES_URL))
	{
		return;
	}

	$container = \Akeeba\Panopticon\Factory::getContainer();
	$container->updateStreamUrl = LOCAL_DEVELOPMENT_UPDATES_URL;
});
