<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Task\AdminToolsTrait;
use Awf\Date\Date;
use Awf\Uri\Uri;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

trait AdminToolsIntegrationTrait
{
	private ?CallbackController $callbackControllerForAdminTools;

	use AdminToolsTrait;

	public function hasAdminToolsPro(): bool
	{
		static $result = null;

		$result ??= $this->hasAdminToolsPro();

		return $result;
	}

	public function adminToolsUnblockIP(string|array $ip): void
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/unblock');

		$ip = array_map(
			fn($x) => is_string($x) ? trim($x) : null,
			is_array($ip) ? $ip : [$ip]
		);

		$options[RequestOptions::FORM_PARAMS] = [
			'ip' => $ip,
		];

		$httpClient->get($url, $options);
	}

	/**
	 * Disable the plugin.
	 *
	 * @return  object|null  Keys: renamed(bool), name(string)
	 * @throws  \GuzzleHttp\Exception\GuzzleException
	 *
	 * @since  1.0.0
	 */
	public function adminToolsPluginDisable(): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/plugin/disable');

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);
		$return = $result?->data?->attributes ?? null;

		$config   = $this->getConfig();
		$oldValue = $config->get('core.admintools.renamed', false);
		$newValue = $result?->renamed ?? $oldValue;
		$config->set('core.admintools.renamed', $newValue);

		if (is_object($return))
		{
			$return->didChange = $oldValue != $newValue;
		}

		return $return;
	}

	public function adminToolsPluginEnable(): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/plugin/enable');

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);
		$return = $result?->data?->attributes ?? null;

		$config   = $this->getConfig();
		$oldValue = $config->get('core.admintools.renamed', false);
		$newValue = $result?->renamed ?? $oldValue;
		$config->set('core.admintools.renamed', $newValue);

		if (is_object($return))
		{
			$return->didChange = $oldValue != $newValue;
		}

		return $return;
	}

	public function adminToolsHtaccessDisable(): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/htaccess/disable');

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);

		return $result?->data?->attributes ?? null;
	}

	public function adminToolsHtaccessEnable(): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/htaccess/enable');

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);

		return $result?->data?->attributes ?? null;
	}

	public function adminToolsTempSuperUser(?Date $expiration = null): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/tempsuperuser');

		if (!is_null($expiration))
		{
			$options[RequestOptions::FORM_PARAMS] = [
				'expiration' => $expiration->toISO8601(),
			];
		}

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);

		return $result?->data?->attributes ?? null;
	}

	public function adminToolsGetScans(int $from = 0, int $limit = 10): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/admintools/scans');

		$uri = new Uri($url);
		$uri->setVar('page[offset]', $from);
		$uri->setVar('page[limit]', $limit);

		$result = json_decode(
			$httpClient->get($uri->toString(), $options)->getBody()->getContents()
		);

		if (empty($result) || !is_object($result))
		{
			return null;
		}

		return (object) [
			'pages' => ((array) ($result?->meta ?? []))['total-pages'] ?? 1,
			'items' => array_map(
				fn(?object $x) => $x?->attributes ?? null,
				$result?->data ?? []
			),
		];
	}

	public function adminToolsGetScanalerts(int $scanId, int $from = 0, int $limit = 10): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions(
			$this,
			sprintf('/index.php/v1/panopticon/admintools/scan/%d', $scanId)
		);

		$uri = new Uri($url);
		$uri->setVar('page[offset]', $from);
		$uri->setVar('page[limit]', $limit);

		$result = json_decode(
			$httpClient->get($uri->toString(), $options)->getBody()->getContents()
		);

		if (empty($result) || !is_object($result))
		{
			return null;
		}

		return (object) [
			'pages' => ((array) ($result?->meta ?? []))['total-pages'] ?? 1,
			'items' => array_map(
				fn(?object $x) => $x?->attributes ?? null,
				$result?->data ?? []
			),
		];
	}

	public function adminToolsGetScanalert(int $scanAlertId): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions(
			$this,
			sprintf('/index.php/v1/panopticon/admintools/scanalert/%d', $scanAlertId)
		);

		$result = json_decode(
			$httpClient->get($url, $options)->getBody()->getContents()
		);

		if (empty($result) || !is_object($result))
		{
			return null;
		}

		return $result?->data?->attributes ?? null;
	}

	/**
	 * Get the cache controller for requests to Akeeba Backup
	 *
	 * @return  CallbackController
	 * @since   1.0.0
	 */
	private function getAdminToolsCacheController(): CallbackController
	{
		if (empty($this->callbackControllerForAdminTools))
		{
			/** @var Container $container */
			$container = $this->container;
			$pool      = $container->cacheFactory->pool('admintools');

			$this->callbackControllerForAdminTools = new CallbackController($container, $pool);
		}

		return $this->callbackControllerForAdminTools;
	}

}