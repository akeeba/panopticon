<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Cache;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
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
	 * Get cached data or execute the callback and cache the result
	 *
	 * @param   callable       $callback      The callback to call if there is no cached data.
	 * @param   array          $args          The arguments to the callback.
	 * @param   string|null    $id            The cache key. NULL to determine automatically.
	 * @param   string|null    $poolName      The name of the cache pool. NULL to use the 'system' pool.
	 * @param   string|null    $namespace     The cache namespace. Not all cache pools support namespaces.
	 * @param   array          $tags          The cache item's tags. Only used when the cache item doesn't already
	 *                                        exist.
	 * @param   callable|null  $serializer    Optional. Callable to serialise the callback's result.
	 * @param   callable|null  $deserializer  Optional. Callable to deserialise the cached data.
	 *
	 * @return  mixed
	 * @throws  InvalidArgumentException
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
		$id ??= $this->makeId($callback, $args);

		if (is_int($expiration))
		{
			$expiration = new \DateInterval(sprintf('PT%dS', $expiration));
		}

		if ($this->pool instanceof CacheInterface)
		{
			// Prefer Symfony Cache Contracts if supported by the pool; they implement stampede prevention.
			$data = $this->pool->get(
				$id,
				function (ItemInterface $item) use ($callback, $args, $serializer, $tags, $expiration) {
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
				}
			);
		}
		else
		{
			// Otherwise, we have a standard PSR-6 cache pool.
			if ($this->pool->hasItem($id))
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

			return sha1($hash . serialize([$args]));
		}

		if (\is_array($callback) && \is_object($callback[0]))
		{
			$vars        = get_object_vars($callback[0]);
			$vars[]      = strtolower(\get_class($callback[0]));
			$callback[0] = $vars;
		}

		return md5(serialize([$callback, $args]));
	}
}