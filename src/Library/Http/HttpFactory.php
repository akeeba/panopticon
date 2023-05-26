<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Http;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;

class HttpFactory
{
	private static $instances = [];

	/**
	 * Makes a new Guzzle HTTP client instance. All parameters are options.
	 *
	 * @param   HandlerStack|null  $stack          The handler stack to use.
	 * @param   array              $clientOptions  The client options.
	 * @param   bool               $cache          Whether to enable caching.
	 * @param   int|null           $cacheTTL       The cache TTL. Leave NULL to respect HTTP headers.
	 * @param   bool               $singleton      Whether to return a Singleton instance.
	 *
	 * @return  Client  The client instance.
	 */
	public function makeClient(
		?HandlerStack $stack = null,
		array         $clientOptions = [],
		bool          $cache = true,
		?int          $cacheTTL = null,
		bool          $singleton = true
	): Client
	{
		$signature = md5(
			($stack ? serialize($stack) : '*NULL*')
			. '#' .
			(!empty($clientOptions) ? serialize($clientOptions) : '*NULL*')
			. '#' .
			($cache ? 'cache' : 'no-cache')
		);

		if ($singleton && isset(self::$instances[$signature]))
		{
			return self::$instances[$signature];
		}

		$stack ??= HandlerStack::create();

		if ($cache)
		{
			$cachePool = new Psr6CacheStorage(
				Factory::getContainer()->cacheFactory->pool('http')
			);

			if ($cacheTTL !== null && $cacheTTL > 0)
			{
				$greedyCacheStrategy = new GreedyCacheStrategy(
					$cachePool,
					$cacheTTL,
					new KeyValueHttpHeader(
						['Authorization', 'X-Joomla-Token']
					)
				);

				$stack->push(new CacheMiddleware($greedyCacheStrategy), 'greedy-cache');
			}
			else
			{
				$cacheStrategy = new PrivateCacheStrategy($cachePool);
				$stack->push(new CacheMiddleware($cacheStrategy), 'cache');
			}

		}

		$clientOptions = array_merge($clientOptions + ['handler' => $stack]);

		$client = new Client(
			$clientOptions
		);

		if ($singleton)
		{
			self::$instances[$signature] = $client;
		}

		return $client;
	}
}