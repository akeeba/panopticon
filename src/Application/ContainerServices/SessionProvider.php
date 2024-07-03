<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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
			$isHTTPS = Uri::getInstance()->getScheme() === 'https';
		}
		catch (\Exception $e)
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
				'sid_length'             => 42,
				'sid_bits_per_character' => 6,
				'lazy_write'             => 1,
			]
		);

		// Use a custom cookie name instead of the generic PHPSESSID
		$manager->setName('panopticon_session');

		// Set the session save path
		$sessionPath = APATH_TMP . '/session';

		if (!@is_dir($sessionPath))
		{
			@mkdir($sessionPath, 0700, true);
		}

		$manager->setSavePath($sessionPath, (int) $appConfig->get('session_save_levels', 0));

		return $manager;
	}
}