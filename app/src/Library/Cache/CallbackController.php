<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Cache;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Namespaced\NamespacedCachePool;
use Cache\TagInterop\TaggableCacheItemInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Callback cache controller.
 *
 * Inspired by Joomla's \Joomla\CMS\Cache\Controller\CallbackController. Made to work with any PSR-16–compatible cache
 * item pool controller. Optimised to work with PHP Cache (https://www.php-cache.com) adapters.
 */
class CallbackController
{
	public function __construct(private Container $container)
	{
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
		?string                          $poolName = null,
		?string                          $namespace = null,
		array                            $tags = [],
		?callable                        $serializer = null,
		?callable                        $deserializer = null
	)
	{
		$id ??= $this->makeId($callback, $args);

		try
		{
			$pool = $poolName
				? $this->container->cacheFactory->pool($poolName)
				: $this->container->cacheFactory->pool();
		}
		catch (\Exception $e)
		{
			$pool = $this->container->cacheFactory->pool();
			$namespace = $poolName . (empty($namespace) ? '' : "|$namespace");
		}

		if ($namespace && $pool instanceof HierarchicalPoolInterface)
		{
			// Forward compatibility; the default file cache doesn't support hierarchy, therefore NamespacedCachePool
			$pool = new NamespacedCachePool($pool, $namespace);
		}
		elseif (!empty($namespace))
		{
			// Pseudo-namespacing...
			$id = sha1($namespace. '_' . $id);
		}

		if ($pool->hasItem($id))
		{
			$data = $pool->getItem($id)->get();
		}
		else
		{
			$data = call_user_func_array($callback, $args);

			if (is_callable($serializer))
			{
				$data = call_user_func($serializer, $data);
			}

			$item = $pool->getItem($id)->set($data);

			if (!empty($tags) && $item instanceof TaggableCacheItemInterface)
			{
				$item->setTags($tags);
			}

			$expiration ??= new \DateInterval(sprintf('PT%dM', $this->container->appConfig->get('caching_time', '60')));

			if (is_int($expiration))
			{
				$expiration = new \DateInterval(sprintf('PT%dS', $expiration));
				$item->expiresAfter($expiration);
			}
			elseif ($expiration instanceof \DateInterval)
			{
				$item->expiresAfter($expiration);
			}
			elseif ($expiration instanceof \DateTime)
			{
				$item->expiresAt($expiration);
			}

			$pool->save($item);
		}

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