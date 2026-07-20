<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Http;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Security\ForbiddenHostException;
use Akeeba\Panopticon\Library\Security\ForbiddenIpRanges;
use Awf\Uri\Uri;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Http\Message\RequestInterface;

class HttpFactory
{
	private static $instances = [];

	public function __construct(private Container $container) {}

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
		/**
		 * A HandlerStack always contains closures, and client options may too (e.g. an on_redirect callback), neither
		 * of which can be serialised. Identify a supplied stack by object identity, and fall back to a non-memoisable
		 * signature when the options cannot be serialised, rather than letting the caller take a fatal error.
		 */
		$optionsSignature = empty($clientOptions) ? '*NULL*' : $this->getOptionsSignature($clientOptions);

		// Options which cannot be serialised cannot be compared, therefore such a client must not be memoised.
		if ($optionsSignature === null)
		{
			$singleton = false;
		}

		$signature = hash(
			'md5',
			($stack ? spl_object_hash($stack) : '*NULL*')
			. '#' .
			($optionsSignature ?? '*UNSERIALISABLE*')
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
				$this->container->cacheFactory->pool('http')
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

		/**
		 * Enforce the operator's forbidden IP ranges on every outbound request.
		 *
		 * This is pushed onto the stack, which places it below Guzzle's redirect middleware. The redirect middleware
		 * re-dispatches each hop through the handlers below it, therefore this guard is re-evaluated for every single
		 * redirect hop and not merely for the initial request. That is what closes the DNS-rebinding and
		 * redirect-pivot bypasses: without per-hop validation any save-time check is defeated by pointing at a
		 * permitted host which then 302s inward.
		 */
		$stack->push($this->makeForbiddenHostMiddleware(), 'forbidden-hosts');

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

	/**
	 * Get a serialised signature for the client options, or NULL if they cannot be serialised.
	 *
	 * Client options may legitimately contain closures — an on_redirect callback, for instance — which serialize()
	 * refuses to handle.
	 *
	 * @param   array  $clientOptions  The client options.
	 *
	 * @return  string|null
	 * @since   1.4.0
	 */
	private function getOptionsSignature(array $clientOptions): ?string
	{
		try
		{
			return serialize($clientOptions);
		}
		catch (\Throwable)
		{
			return null;
		}
	}

	/**
	 * Create the Guzzle middleware which refuses requests to operator-forbidden IP ranges.
	 *
	 * The configuration is read inside the request closure rather than when the middleware is built. Clients are
	 * memoised by signature, so reading it at build time would pin a stale policy for the lifetime of the process.
	 *
	 * @return  callable
	 * @since   1.4.0
	 */
	private function makeForbiddenHostMiddleware(): callable
	{
		return function (callable $handler): callable {
			return function (RequestInterface $request, array $options) use ($handler) {
				$forbidden = ForbiddenIpRanges::fromConfig($this->container);

				// The policy is disabled by default. Do no work — and no DNS lookup — when there are no ranges.
				if ($forbidden->isEmpty())
				{
					return $handler($request, $options);
				}

				$host = $request->getUri()->getHost();

				if ($host !== '' && $forbidden->isForbiddenHost($host))
				{
					/**
					 * The thrown message is generic so that it cannot be used to enumerate the operator's range list.
					 * The detail the operator needs in order to diagnose a complaint goes to the log instead.
					 */
					$this->container->loggerFactory->get('panopticon')->notice(
						'Refused outbound request: forbidden host.'
					);

					throw new ForbiddenHostException($host);
				}

				return $handler($request, $options);
			};
		};
	}

	/**
	 * Get the default request options.
	 *
	 * This currently takes into account the following:
	 * - **Proxy settings** defined in the application configuration
	 * - **SSL CA bundle** defined in the AKEEBA_CACERT_PEM constant in the bootstrap.php file
	 *
	 * @return array
	 */
	public function getDefaultRequestOptions(): array
	{
		$options = [];

		$proxySettings = $this->getProxySettings();

		if (!empty($proxySettings))
		{
			$options[RequestOptions::PROXY] = $proxySettings;
		}

		if (defined('AKEEBA_CACERT_PEM'))
		{
			$options[RequestOptions::VERIFY] = AKEEBA_CACERT_PEM;
		}

		return $options;
	}

	/**
	 * Construct the Guzzle proxy settings based on the application configuration.
	 *
	 * @return array|null The proxy settings; NULL if no proxy is defined and/or requested.
	 * @see https://docs.guzzlephp.org/en/stable/request-options.html#proxy
	 */
	private function getProxySettings(): ?array
	{
		// Get the application configuration variables
		$config  = $this->container->appConfig;
		$enabled = $config->get('proxy_enabled', false);
		$host    = trim($config->get('proxy_host', ''));
		$port    = (int) $config->get('proxy_port', 0);
		$user    = $config->get('proxy_user', '');
		$pass    = $config->get('proxy_pass', '');
		$noProxy = $config->get('proxy_no', '');

		// Are we really enabled and ready to use a proxy server?
		$enabled = $enabled && !empty($host) && is_int($port) && $port > 0 && $port < 65536;

		if (!$enabled)
		{
			return null;
		}

		// Construct the proxy URL out of the individual components
		$proxyUri = new Uri('http://' . $host);
		$proxyUri->setPort($port);

		if (!empty($user) && !empty($pass))
		{
			$proxyUri->setUser($user);
			$proxyUri->setPass($pass);
		}

		$proxyUrl = $proxyUri->toString(['scheme', 'user', 'pass', 'host', 'port']);

		// Get the no proxy domain names
		if (!is_array($noProxy))
		{
			$noProxy = explode(',', $noProxy);
			$noProxy = array_map('trim', $noProxy);
			$noProxy = array_filter($noProxy);
		}

		// Construct and return the Guzzle proxy settings
		$proxySettings = [
			'http'  => $proxyUrl,
			'https' => $proxyUrl,
		];

		if (!empty($noProxy))
		{
			$proxySettings['no'] = $noProxy;
		}

		return $proxySettings;
	}
}