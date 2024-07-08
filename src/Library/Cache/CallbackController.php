<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Cache;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Callback cache controller.
 *
 * Inspired by Joomla's \Joomla\CMS\Cache\Controller\CallbackController. Made to work with any PSR-16–compatible cache
 * item pool controller. Optimised to work with PHP Cache (https://www.php-cache.com) adapters.
 */
class CallbackController
{
	public function __construct(private ?Container $container = null, private CacheInterface|CacheItemPoolInterface|null $pool = null)
	{
		$this->container ??= Factory::getContainer();
		$this->pool      ??= $this->container->cacheFactory->pool();
	}

	/**
	 * Get cached data or execute the callback and cache the result.
	 *
	 * In the default case, where a Symfony Cache adapter is used, this is a simple proxy to the Symfony Cache
	 * contract's  \Symfony\Contracts\Cache\CacheInterface::get() method. When a generic PSR-6 cache pool object is
	 * used we implement a similar approach, **WITHOUT** cache stampede protection.
	 *
	 * Using a serializer and deserializer is not ideal. It's best to use a Marshaller in the Symfony Cache
	 * contract object instead. However, this is only available for Symfony Cache objects, not generic PSR-6 cache
	 * pools.
	 *
	 * @param   callable                          $callback      The callback to call if there is no cached data.
	 * @param   array                             $args          The arguments to the callback.
	 * @param   string|null                       $id            The cache key. NULL to determine automatically.
	 * @param   \DateInterval|\DateTime|int|null  $expiration    Expiration date, or cache lifetime. NULL for global
	 *                                                           default. 0 to force re-caching regardless of
	 *                                                           expiration. Integers express seconds.
	 * @param   array                             $tags          The cache item's tags. Only used when the cache item
	 *                                                           doesn't already exist.
	 * @param   callable|null                     $serializer    Optional. Callable to serialise the callback's result.
	 * @param   callable|null                     $deserializer  Optional. Callable to deserialise the cached data.
	 *
	 * @return  mixed
	 * @throws InvalidArgumentException
	 * @throws CacheException
	 */
	public function get(
		callable                         $callback,
		array                            $args = [],
		?string                          $id = null,
		\DateInterval|\DateTime|int|null $expiration = null,
		array                            $tags = [],
		?callable                        $serializer = null,
		?callable                        $deserializer = null
	)
	{
		$id         ??= $this->makeId($callback, $args);
		$forceCache = false;

		if (is_int($expiration))
		{
			if ($expiration === 0)
			{
				$forceCache = true;
			}
			else
			{
				$expiration = new \DateInterval(sprintf('PT%dS', $expiration));
			}
		}

		if ($this->pool instanceof CacheInterface)
		{
			$beta = $forceCache ? INF : 0;
			// Prefer Symfony Cache Contracts if supported by the pool; they implement stampede prevention.
			$data = $this->pool->get(
				key: $id,
				callback: function (ItemInterface $item) use ($callback, $args, $serializer, $tags, $expiration) {
					$data = call_user_func_array($callback, $args);

					// This is icky. Using a Marshaller is preferable, but Marshallers only apply to Symfony Cache.
					if (is_callable($serializer))
					{
						$data = call_user_func($serializer, $data);
					}

					if (!empty($tags))
					{
						$item->tag($tags);
					}

					if ($expiration instanceof \DateInterval)
					{
						$item->expiresAfter($expiration);
					}
					elseif ($expiration instanceof \DateTime)
					{
						$item->expiresAt($expiration);
					}

					return $data;
				},
				beta: $beta,
			);
		}
		else
		{
			// Otherwise, we have a standard PSR-6 cache pool.
			if (!$forceCache && $this->pool->hasItem($id))
			{
				$data = $this->pool->getItem($id)->get();
			}
			else
			{
				/**
				 * Standard PSR-6 cache pools don't have a default expiration time implementation. Therefore, we have to
				 * ensure manually that a default expiration time is implemented. Otherwise, items without an explicit
				 * expiration time / lifetime would end up being cached forever.
				 */
				$expiration ??= new \DateInterval(sprintf('PT%dM', $this->container->appConfig->get('caching_time', '60')));

				$data = call_user_func_array($callback, $args);

				// This is icky. Using a Marshaller is preferable, but Marshallers only apply to Symfony Cache.
				if (is_callable($serializer))
				{
					$data = call_user_func($serializer, $data);
				}

				$item = $this->pool->getItem($id)->set($data);

				/**
				 * Yeah, tagging is only available when using Symfony Cache. If you sub it out for another PSR-6 cache
				 * pool you're on your own.
				 */
				if (!empty($tags) && $item instanceof \Symfony\Contracts\Cache\ItemInterface)
				{
					$item->tag($tags);
				}

				if ($expiration instanceof \DateInterval)
				{
					$item->expiresAfter($expiration);
				}
				elseif ($expiration instanceof \DateTime)
				{
					$item->expiresAt($expiration);
				}

				$this->pool->save($item);
			}
		}

		// This is icky. Using a Marshaller is preferable, but Marshallers only apply to Symfony Cache.
		if (is_callable($deserializer))
		{
			$data = call_user_func($deserializer, $data);
		}

		return $data;
	}

	protected function makeId(callable $callback, array $args = []): string
	{
		// Closures can't be serialized. Instead, we use the object hash.
		if ($callback instanceof \closure)
		{
			$hash = spl_object_hash($callback);

			return hash('sha1', $hash . serialize([$args]));
		}

		if (\is_array($callback) && \is_object($callback[0]))
		{
			$vars        = get_object_vars($callback[0]);
			$vars[]      = strtolower(\get_class($callback[0]));
			$callback[0] = $vars;
		}

		return hash('md5', serialize([$callback, $args]));
	}
}