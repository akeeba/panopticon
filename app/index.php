<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Panopticon\Factory;

define('AKEEBA', 1);

// Load prerequisites
require __DIR__ . '/defines.php';
require APATH_ROOT . '/version.php';
require APATH_ROOT . '/includes/bootstrap.php';

// Wrap execution in a callable to avoid polluting the global namespace with variables
call_user_func(
	function ($app)
	{
		$app->initialise();
		$app->route();
		$app->dispatch();
		$app->render();
		$app->close();
	},
	Factory::getApplication()
);
