<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application\ContainerServices;


use Awf\Container\Container;
use Awf\Session\CsrfTokenFactory;
use Awf\Session\Manager;
use Awf\Session\SegmentFactory;

defined('AKEEBA') || die;

class SessionProvider
{
	public function __invoke(Container $c): Manager
	{
		return new Manager(
			new SegmentFactory(),
			new CsrfTokenFactory(),
			$_COOKIE,
			[
				'save_handler'           => 'files',
				'serialize_handler'      => 'php_serialize',
				'cookie_lifetime'        => $c->appConfig->get('session_timeout', 1440) * 60,
				'cookie_httponly'        => $this->cookie_params['httponly'] ?? 1,
				'use_strict_mode'        => 0,
				'use_cookies'            => 1,
				'cache_limiter'          => 'nocache',
				'use_trans_sid'          => 0,
				'sid_length'             => 42,
				'sid_bits_per_character' => 6,
				'lazy_write'             => 1,
			]
		);
	}
}