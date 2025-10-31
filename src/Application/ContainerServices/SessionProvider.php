<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application\ContainerServices;


use Akeeba\Panopticon\Library\Session\SegmentFactory;
use Awf\Container\Container;
use Awf\Session\CsrfTokenFactory;
use Awf\Session\Manager;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

class SessionProvider
{
	public function __invoke(Container $c): Manager
	{
		try
		{
			$isHTTPS = isset($_SERVER['HTTP_HOST']) && Uri::getInstance()->getScheme() === 'https';
		}
		catch (\Exception)
		{
			$isHTTPS = false;
		}

		$appConfig = $c->appConfig;

		$manager = new Manager(
			new SegmentFactory(),
			new CsrfTokenFactory(),
			$_COOKIE,
			[
				'save_handler'           => 'files',
				'serialize_handler'      => 'php_serialize',
				'cookie_lifetime'        => max($appConfig->get('session_timeout', 1440) * 60, 86400),
				'gc_maxlifetime'         => max($appConfig->get('session_timeout', 1440) * 60, 86400),
				'gc_probability'         => 1,
				'gc_divisor'             => 100,
				'cookie_httponly'        => 1,
				'cookie_secure'          => $isHTTPS ? 1 : 0,
				'cookie_samesite'        => 'Strict',
				'use_strict_mode'        => 1,
				'use_cookies'            => 1,
				'use_only_cookies'       => 1,
				'cache_limiter'          => 'nocache',
				'use_trans_sid'          => 0,
				'lazy_write'             => 1,
			]
		);

		// Use a custom cookie name instead of the generic PHPSESSID
		$manager->setName('panopticon_session');

		// Set the session save path
		$sessionPath = $manager->getSavePath();
		$levels      = 0;

		if (
			!$appConfig->get('session_use_default_path', true)
			|| !@is_dir($sessionPath)
			|| !@is_writable($sessionPath))
		{
			$sessionPath = APATH_TMP . '/session';
			$levels      = (int) $appConfig->get('session_save_levels', 0);

			$this->createOrUpdateFolder($c, $sessionPath);
		}

		$manager->setSavePath($sessionPath, $levels);

		return $manager;
	}

	public function createOrUpdateFolder(Container $c, string $path): void
	{
		$fs = $c->fileSystem;

		if (!@is_dir($path))
		{
			$fs->mkdir($path, 0700);
		}

		if (!is_writable($path))
		{
			$fs->chmod($path, 0777);
		}

		if (
			!@file_exists($path . '/.htaccess')
			|| !@file_exists($path . '/web.config')
		)
		{
			$fs->copy($c->basePath . '/.htaccess', $path . '/.htaccess');
			$fs->copy($c->basePath . '/web.config', $path . '/web.config');

			$fs->chmod($path . '/.htaccess', 0644);
			$fs->chmod($path . '/web.config', 0644);
		}
	}
}