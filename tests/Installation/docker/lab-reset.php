<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

// No defined('AKEEBA') || die; guard: this script runs standalone inside the lab container and never loads the app.

/**
 * Resets the installation lab: fresh database, empty web root, freshly unzipped release, empty logs.
 *
 * Usage: php /lab/lab-reset.php [--readonly]
 */

const WEB_ROOT = '/var/www/html';
const ZIP_FILE = '/lab/panopticon.zip';
const LOG_FILES = ['/var/log/php/error.log', '/var/log/apache2/error.log'];

function fail(string $message): never
{
	fwrite(STDERR, 'RESET FAILED: ' . $message . PHP_EOL);

	exit(1);
}

function run(string $command): void
{
	exec($command . ' 2>&1', $output, $code);

	if ($code !== 0)
	{
		fail(sprintf('`%s` exited with %d: %s', $command, $code, implode(' ', $output)));
	}
}

$readOnly = in_array('--readonly', $argv ?? [], true);

// 1. Database
mysqli_report(MYSQLI_REPORT_OFF);

$db = null;

for ($i = 0; $i < 30 && !$db; $i++)
{
	$db = @mysqli_connect('db', 'root', 'installtest');

	if (!$db)
	{
		sleep(1);
	}
}

if (!$db)
{
	fail('Cannot connect to the database as root: ' . mysqli_connect_error());
}

foreach ([
	'DROP DATABASE IF EXISTS `panopticon`',
	'CREATE DATABASE `panopticon` DEFAULT CHARACTER SET utf8mb4',
	"CREATE USER IF NOT EXISTS 'panopticon'@'%' IDENTIFIED BY 'panopticon'",
	"GRANT ALL PRIVILEGES ON `panopticon`.* TO 'panopticon'@'%'",
	'FLUSH PRIVILEGES',
] as $sql)
{
	if (!$db->query($sql))
	{
		fail(sprintf('`%s` failed: %s', $sql, $db->error));
	}
}

$db->close();

// 2. Web root
if (!is_file(ZIP_FILE))
{
	fail(ZIP_FILE . ' is missing');
}

run('find ' . escapeshellarg(WEB_ROOT) . ' -mindepth 1 -delete');
run('unzip -q -o ' . escapeshellarg(ZIP_FILE) . ' -d ' . escapeshellarg(WEB_ROOT));

if ($readOnly)
{
	run('chown -R root:root ' . escapeshellarg(WEB_ROOT));
	run('chmod -R a+rX ' . escapeshellarg(WEB_ROOT));
	// The base image makes the web root world-writable (1777); a read-only install must not be.
	run('chmod 0755 ' . escapeshellarg(WEB_ROOT));

	foreach (['cache', 'log', 'tmp'] as $dir)
	{
		$path = WEB_ROOT . '/' . $dir;

		if (!is_dir($path) && !mkdir($path, 0755))
		{
			fail('Cannot create ' . $path);
		}

		run('chown -R www-data:www-data ' . escapeshellarg($path));
	}
}
else
{
	run('chown -R www-data:www-data ' . escapeshellarg(WEB_ROOT));
}

// 3. Logs
foreach (LOG_FILES as $logFile)
{
	if (is_file($logFile) && file_put_contents($logFile, '') === false)
	{
		fail('Cannot truncate ' . $logFile);
	}
}

echo 'RESET OK' . PHP_EOL;

exit(0);
