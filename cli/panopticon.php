#!/usr/bin/env php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Application;

const AKEEBA = 1;
const AKEEBA_CLI = 1;

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

	if (file_exists(APATH_USER_CODE . '/early_bootstrap.php'))
	{
		require_once APATH_USER_CODE . '/early_bootstrap.php';
	}

	require APATH_ROOT . '/includes/bootstrap.php';

	/**
	 * DO NOT REMOVE.
	 *
	 * The following line initialises the AWF application which is used to return the AWF Container internally in AWF.
	 */
	Factory::getApplication();

	$application = new Application();
	$application->setName('Akeeba Panopticon CLI');
	$application->setVersion(AKEEBA_PANOPTICON_VERSION);

	// Automatically populate the commands
	foreach ([
		         __DIR__ . '/../user_code/CliCommand',
		         __DIR__ . '/../src/CliCommand',
			 ] as $directory)
	{
		if (!@is_dir($directory) || !is_readable($directory))
		{
			continue;
		}

		$di = new DirectoryIterator($directory);

		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->getExtension() !== 'php')
			{
				continue;
			}

			$className = '\\Akeeba\\Panopticon\\CliCommand\\' . $file->getBasename('.php');

			if (!class_exists($className))
			{
				continue;
			}

			if (!(new ReflectionClass($className))->isInstantiable())
			{
				continue;
			}

			$application->add(new $className());
		}
	}

	$application->run();
});