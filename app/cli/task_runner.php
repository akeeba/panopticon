<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Task;
use Awf\Mvc\Model;

const AKEEBA = 1;

// Make sure we're running under the PHP CLI SAPI
if (php_sapi_name() !== 'cli')
{
	header('HTTP/1.1 403 Forbidden');

	exit();
}

call_user_func(function () {
	// Load prerequisites
	require __DIR__ . '/../defines.php';
	require APATH_ROOT . '/version.php';
	require APATH_ROOT . '/includes/bootstrap.php';

	$container = Factory::getContainer();

	if (!file_exists($container->appConfig->getDefaultPath()))
	{
		echo "You need to configure Akeeba Panopticon before running this script." . PHP_EOL;

		exit(125);
	}

	/**
	 * @var  Task $model The Task model.
	 *
	 * IMPORTANT! We deliberately use the PHP 5.x / 7.x calling convention.
	 *
	 * Using the PHP 8.x and later calling convention with named parameters does not allow graceful termination on older
	 * PHP versions.
	 */
	$model = Model::getTmpInstance(null, 'Task', $container);
	$timer = new Awf\Timer\Timer(
		$container->appConfig->get('max_execution', 60),
		$container->appConfig->get('execution_bias', 75)
	);

	while ($timer->getTimeLeft() > 0.01)
	{
		if (!$model->runNextTask())
		{
			break;
		}
	}
});