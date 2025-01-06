<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * =====================================================================================================================
 * Local Development Helper
 * =====================================================================================================================
 *
 * To use this, create the file user_code/bootstrap.php with these contents:
 *
 * require_once __DIR__ . '/../assets/localdev-bootstrap.php';
 *
 * To customise its behaviour define the respective control constants at the top of the user_code/bootstrap.php file,
 * before requiring this file here.
 *
 * =====================================================================================================================
 */

use Akeeba\Panopticon\Factory;

// Override values in the application Container
call_user_func(
	function () {
		$container = Factory::getContainer();

		if (defined('LOCAL_DEVELOPMENT_UPDATES_URL') && !empty(LOCAL_DEVELOPMENT_UPDATES_URL))
		{
			$container->updateStreamUrl = LOCAL_DEVELOPMENT_UPDATES_URL;
		}

		if (defined('LOCAL_DEVELOPMENT_STATS_URL') && !empty(LOCAL_DEVELOPMENT_STATS_URL))
		{
			$container->usageStatsUrl = LOCAL_DEVELOPMENT_STATS_URL;
		}
	}
);
