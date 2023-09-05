<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Application;
use Akeeba\Panopticon\Factory;

const AKEEBA = 1;

call_user_func(function (){
	// Load prerequisites
	require __DIR__ . '/defines.php';
	require APATH_ROOT . '/version.php';
	require APATH_ROOT . '/includes/bootstrap.php';

	$app = Factory::getApplication();

	if (method_exists(Application::class, 'setInstance'))
	{
		Application::setInstance('panopticon', $app);
	}

	$app->initialise();
	$app->route();
	$app->dispatch();
	$app->render();
	$app->close();
});
