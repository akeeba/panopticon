<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Cache;


use Akeeba\Panopticon\Container;
use Awf\Database\Driver;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\CacheInterface;

defined('AKEEBA') || die;

class CacheFactory
{
	private static $instances = [];

	public function __construct(private Container $container)
	{
	}

	public function pool($poolName = 'system'): CacheInterface
	{
		if (!$this->isValid($poolName))
		{
			throw new \InvalidArgumentException('Invalid cache pool name', 500);
		}

		if (isset(self::$instances[$poolName]))
		{
			return self::$instances[$poolName];
		}

		$appConfig          = $this->container->appConfig;
		$cacheAdapter       = $appConfig->get('cache_adapter', 'filesystem');
		$cacheTimeInSeconds = (int) $appConfig->get('caching_time', 60) * 60;

		switch ($cacheAdapter)
		{
			case 'filesystem':
			default:
				self::$instances[$poolName] = new TagAwareAdapter(
					new FilesystemAdapter(
						namespace: $poolName,
						defaultLifetime: $cacheTimeInSeconds,
						directory: APATH_CACHE
					)
				);
				break;

			case 'linuxfs':
				self::$instances[$poolName] = new FilesystemTagAwareAdapter(
					namespace: $poolName,
					defaultLifetime: $cacheTimeInSeconds,
					directory: APATH_CACHE
				);
				break;

			case 'redis':
				self::$instances[$poolName] = new RedisAdapter(
					redis: RedisAdapter::createConnection($appConfig->get('caching_redis_dsn', '')),
					namespace: $poolName,
					defaultLifetime: $cacheTimeInSeconds,
				);
				break;

			case 'memcached':
				self::$instances[$poolName] = new MemcachedAdapter(
					client: MemcachedAdapter::createConnection($appConfig->get('caching_memcached_dsn', '')),
					namespace: $poolName,
					defaultLifetime: $cacheTimeInSeconds,
				);
				break;

			case 'db':
				$driver = $appConfig->get('dbdriver', 'mysql');

				if ($driver === 'pdomysql')
				{
					$db = $this->container->db;
				}
				else
				{
					$options = [
						'driver'   => 'pdomysql',
						'database' => $appConfig->get('dbname'),
						'select'   => $appConfig->get('dbselect', true),
						'host'     => $appConfig->get('dbhost'),
						'user'     => $appConfig->get('dbuser'),
						'password' => $appConfig->get('dbpass'),
						'prefix'   => $appConfig->get('prefix'),
						'ssl'      => [],
					];

					$db = Driver::fromOptions($options);
				}

				self::$instances[$poolName] = new PdoAdapter(
					connOrDsn: $db->getConnection(),
					namespace: $poolName,
					defaultLifetime: $cacheTimeInSeconds
				);

				break;
		}


		return self::$instances[$poolName];
	}

	private function isValid(mixed $poolName)
	{
		// Anything starting with a dot; catches ., .., and *nix hidden files/folders including .htaccess and .gitignore
		if (str_starts_with($poolName, '.'))
		{
			return false;
		}

		// Anything outside the cross-platform safe filename characters. Note that **only** latin-1 alphas are allowed.
		if (!preg_match('/[a-zA-Z0-9\-_!@#$%^&()\[\]{};\',.]/', $poolName))
		{
			return false;
		}

		// Windows does not allow filenames ending with a space or dot, so let's prevent their use
		if (str_ends_with($poolName, " ") || str_ends_with($poolName, "."))
		{
			return false;
		}

		// Reserved filenames across Windows and *nix systems, plus web.config (shipped with Panopticon)
		if (
			array_reduce(
				[
					'CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
					'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9', 'STDIN', 'STDOUT',
					'WEB.CONFIG',
				],
				fn(bool $carry, string $item) => $carry || str_starts_with(strtoupper($poolName), $item),
				false
			)
		)
		{
			return false;
		}

		return true;
	}
}