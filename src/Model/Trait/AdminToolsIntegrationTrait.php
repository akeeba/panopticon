<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\Trait\AdminToolsTrait;
use Awf\Date\Date;
use Awf\Exception\App;
use Awf\Mvc\DataModel\Collection;
use Awf\Uri\Uri;
use Awf\User\User;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Throwable;

/**
 * Model Trait for the integration with Admin Tools Professional for Joomla!
 *
 * @since  1.0.0
 */
trait AdminToolsIntegrationTrait
{
	private ?CallbackController $callbackControllerForAdminTools;

	use AdminToolsTrait;

	public function hasAdminToolsPro(): bool
	{
		static $result = null;

		$result ??= $this->hasAdminTools($this, true);

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
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/unblock');

		$ip = array_map(
			fn($x) => is_string($x) ? trim($x) : null,
			is_array($ip) ? $ip : [$ip]
		);

		$options[RequestOptions::FORM_PARAMS] = [
			'ip' => $ip,
		];

		$httpClient->post($url, $options);
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
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/plugin/disable');

		$result = json_decode(
			$this->sanitizeJson($httpClient->post($url, $options)->getBody()->getContents())
		);
		$return = $this->adminToolsExtractResult($result);

		$config   = $this->getConfig();
		$oldValue = $config->get('core.admintools.renamed', false);
		$newValue = $return?->renamed ?? $oldValue;
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
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/plugin/enable');

		$result = json_decode(
			$this->sanitizeJson($httpClient->post($url, $options)->getBody()->getContents())
		);
		$return = $this->adminToolsExtractResult($result);

		$config   = $this->getConfig();
		$oldValue = $config->get('core.admintools.renamed', false);
		$newValue = $return?->renamed ?? $oldValue;
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
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/htaccess/disable');

		$result = json_decode(
			$this->sanitizeJson($httpClient->post($url, $options)->getBody()->getContents())
		);

		return $this->adminToolsExtractResult($result);
	}

	public function adminToolsHtaccessEnable(): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/htaccess/enable');

		$result = json_decode(
			$this->sanitizeJson($httpClient->post($url, $options)->getBody()->getContents())
		);

		return $this->adminToolsExtractResult($result);
	}

	public function adminToolsTempSuperUser(?Date $expiration = null): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/tempsuperuser');

		if (!is_null($expiration))
		{
			$options[RequestOptions::FORM_PARAMS] = [
				'expiration' => $expiration->toISO8601(),
			];
		}

		$result = json_decode(
			$this->sanitizeJson($httpClient->post($url, $options)->getBody()->getContents())
		);

		return $this->adminToolsExtractResult($result);
	}

	public function adminToolsGetScans(bool $cache = true, int $from = 0, int $limit = 10): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		$controller = $this->getAdminToolsCacheController();

		return $controller->get(
			callback: function (int $from, int $limit) {
				/** @var Client $httpClient */
				$httpClient = $this->container->httpFactory->makeClient(cache: false);
				[$url, $options] = $this->adminToolsGetRequestOptions('/v1/panopticon/admintools/scans');

				$uri = new Uri($url);
				$uri->setVar('page[offset]', $from);
				$uri->setVar('page[limit]', $limit);

				$result = json_decode(
					$this->sanitizeJson($httpClient->get($uri->toString(), $options)->getBody()->getContents())
				);

				if (empty($result) || !is_object($result))
				{
					return null;
				}

				return (object) [
					'pages' => ((array) ($result?->meta ?? []))['total-pages'] ?? 1,
					'items' => array_map(
						fn(?object $x) => match ($this->cmsType()) {
							CMSType::JOOMLA => $x?->attributes ?? null,
							CMSType::WORDPRESS => $x ?? null,
							CMSType::UNKNOWN => null
						},
						$result?->data ?? []
					),
				];
			},
			args: [$from, $limit],
			id: sprintf('scans-%d-%d-%d', $this->getId(), $from, $limit),
			expiration: $cache ? null : 0
		);
	}

	public function adminToolsGetScanalerts(int $scanId, int $from = 0, int $limit = 10): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		$controller = $this->getAdminToolsCacheController();

		return $controller->get(
			callback: function (int $scanId, int $from, int $limit): ?object {
				/** @var Client $httpClient */
				$httpClient = $this->container->httpFactory->makeClient(cache: false);
				[$url, $options] = $this->adminToolsGetRequestOptions(
					sprintf('/v1/panopticon/admintools/scan/%d', $scanId)
				);

				$uri = new Uri($url);
				$uri->setVar('page[offset]', $from);
				$uri->setVar('page[limit]', $limit);

				$result = json_decode(
					$this->sanitizeJson($httpClient->get($uri->toString(), $options)->getBody()->getContents())
				);

				if (empty($result) || !is_object($result))
				{
					return null;
				}

				return (object) [
					'pages' => ((array) ($result?->meta ?? []))['total-pages'] ?? 1,
					'items' => array_map(
						fn(?object $x) => match ($this->cmsType()) {
							CMSType::JOOMLA => $x?->attributes ?? null,
							CMSType::WORDPRESS => $x ?? null,
							CMSType::UNKNOWN => null
						},
						$result?->data ?? []
					),
				];
			},
			args: [$scanId, $from, $limit],
			id: sprintf('scanalerts-%d-%d-%d-%d', $this->getId(), $scanId, $from, $limit)
		);
	}

	public function adminToolsGetScanalert(int $scanAlertId): ?object
	{
		if (!$this->hasAdminToolsPro())
		{
			throw new \RuntimeException('This site does not have Admin Tools Professional installed.');
		}

		$controller = $this->getAdminToolsCacheController();

		return $controller->get(
			callback: function (int $scanAlertId): ?object {
				/** @var Client $httpClient */
				$httpClient = $this->container->httpFactory->makeClient(cache: false);
				[$url, $options] = $this->adminToolsGetRequestOptions(
					sprintf('/v1/panopticon/admintools/scanalert/%d', $scanAlertId)
				);

				$result = json_decode(
					$this->sanitizeJson($httpClient->get($url, $options)->getBody()->getContents())
				);

				if (empty($result) || !is_object($result))
				{
					return null;
				}

				return match ($this->cmsType()) {
					CMSType::JOOMLA => $result?->data?->attributes ?? null,
					CMSType::WORDPRESS => $result?->data ?? null,
					CMSType::UNKNOWN => null
				};
			},
			args: [$scanAlertId],
			id: sprintf('scanalert-%d', $scanAlertId),
			expiration: $this->container->dateFactory()->add(new \DateInterval('P1Y'))
		);
	}

	public function adminToolsGetAllScheduledTasks(): Collection
	{
		return $this->getSiteSpecificTasks('filescanner');
	}

	/**
	 * Get the enqueued PHP File Change Scanner tasks
	 *
	 * @return  Collection
	 * @since   1.0.0
	 */
	public function adminToolsGetEnqueuedTasks(): Collection
	{
		return $this->getSiteSpecificTasks('filescanner')
			->filter(
				function (Task $task) {
					$params = $task->getParams();

					// Mast not be running, or waiting to run
					if (in_array(
						$task->last_exit_code, [
							Status::INITIAL_SCHEDULE->value,
							Status::WILL_RESUME->value,
							Status::RUNNING->value,
						]
					))
					{
						return false;
					}

					// Must be a run-once task
					if (empty($params->get('run_once')))
					{
						return false;
					}

					// Must be a generated task, not a user-defined backup schedule
					if (empty($params->get('enqueued_scan')))
					{
						return false;
					}

					// Its next execution date must be empty or in the past
					if (empty($task->last_execution))
					{
						return true;
					}

					try
					{
						$date = $this->container->dateFactory($task->last_execution, 'UTC');
					}
					catch (Throwable)
					{
						return true;
					}

					$now  = $this->container->dateFactory();

					return ($date < $now);
				}
			);
	}

	/**
	 * Enqueue a new PHP File Change Scanner scan
	 *
	 * @return  void
	 * @throws  App
	 * @since   1.0.0
	 */
	public function adminToolsScanEnqueue(?User $user = null): void
	{
		// Try to find an akeebabackup task object which is run once, not running / initial schedule, and matches the specifics
		$tasks = $this->adminToolsGetEnqueuedTasks();

		if ($tasks->count())
		{
			$task = $tasks->first();
		}
		else
		{
			$task = Task::getTmpInstance('', 'Task', $this->container);
		}

		try
		{
			$tz = $this->container->appConfig->get('timezone', 'UTC');

			// Do not remove. This tests the validity of the configured timezone.
			new DateTimeZone($tz);
		}
		catch (Exception)
		{
			$tz = 'UTC';
		}

		$runDateTime = $this->container->dateFactory('now', $tz);
		$runDateTime->add(new \DateInterval('PT2S'));

		$task->save(
			[
				'site_id'         => $this->getId(),
				'type'            => 'filescanner',
				'params'          => json_encode(
					[
						'run_once'       => 'disable',
						'enqueued_scan'  => 1,
						'initiatingUser' => $user?->getId(),
					]
				),
				'cron_expression' => $runDateTime->minute . ' ' . $runDateTime->hour . ' ' . $runDateTime->day . ' ' .
				                     $runDateTime->month . ' ' . $runDateTime->dayofweek,
				'enabled'         => 1,
				'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
				'last_execution'  => (clone $runDateTime)->sub(new \DateInterval('PT1M'))->toSql(),
				'last_run_end'    => null,
				'next_execution'  => $runDateTime->toSql(),
				'locked'          => null,
				'priority'        => 1,
			]
		);
	}

	public function adminToolsWordPressPluginSlug(): ?string
	{
		if ($this->cmsType() !== CMSType::WORDPRESS)
		{
			return null;
		}

		$items = array_filter(
			(array) $this->getConfig()->get('extensions.list'),
			fn(object $item) => $item->type === 'plugin'
			                    && $item->element === 'admintoolswp.php'
			                    && str_contains($item->name, 'Professional')
		);

		if (empty($items))
		{
			return null;
		}

		$item = array_pop($items);

		return $item?->extension_id ?? null;
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

	private function adminToolsGetRequestOptions(string $path): array
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->getRequestOptions($this, '/index.php' . $path),
			CMSType::WORDPRESS => $this->getRequestOptions($this, $path),
			default => [null, null],
		};
	}

	private function adminToolsExtractResult(mixed $result): mixed
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $result?->data?->attributes ?? null,
			CMSType::WORDPRESS => $result,
			default => null,
		};
	}
}