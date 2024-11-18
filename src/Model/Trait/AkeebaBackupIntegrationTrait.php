<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\BackupJsonApi\Connector;
use Akeeba\BackupJsonApi\Exception\RemoteError;
use Akeeba\BackupJsonApi\HttpAbstraction\HttpClientGuzzle;
use Akeeba\BackupJsonApi\HttpAbstraction\HttpClientInterface;
use Akeeba\BackupJsonApi\Options as JsonApiOptions;
use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupInvalidBody;
use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupNoEndpoint;
use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupNotInstalled;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Logger\MemoryLogger;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupCannotConnectException;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupIsNotPro;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupNoInfoException;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Awf\Mvc\DataModel\Collection;
use Awf\User\User;
use Composer\CaBundle\CaBundle;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Model Trait for the integration with Akeeba Backup Professional for Joomla!
 *
 * @since  1.0.0
 */
trait AkeebaBackupIntegrationTrait
{
	private ?CallbackController $callbackControllerForAkeebaBackup = null;

	/**
	 * Test the connection to the remote site's Akeeba Backup installation.
	 *
	 * First, we use the API to get information about whether Akeeba Backup is installed and has the JSON API available.
	 *
	 * If so, we test the endpoints returned by the API to see to which one and how we can connect.
	 *
	 * Note: When `$throw` is enabled we throw an exception immediately upon encountering an error.
	 *
	 * @param   bool  $withEndpoints  Should I also test the endpoints?
	 * @param   bool  $throw          Throw exceptions describing the error conditions.
	 *
	 * @return  bool  True if the site's configuration must be saved.
	 * @since   1.0.0
	 */
	public function testAkeebaBackupConnection(bool $withEndpoints = true, bool $throw = false): bool
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->container;
		$session   = $container->segment;

		// Initialise debug information
		$session->set('testconnection.akeebabackup.step', null);
		$session->set('testconnection.akeebabackup.http_status', null);
		$session->set('testconnection.akeebabackup.body', null);
		$session->set('testconnection.akeebabackup.headers', null);
		$throwThis = null;

		// Get the information from the API
		$session->set('testconnection.akeebabackup.step', 'Retrieve Akeeba Backup connection information from the API');

		$client = $container->httpFactory->makeClient(cache: false, singleton: false);

		[$url, $options] = match ($this->cmsType())
		{
			CMSType::JOOMLA => $this->getRequestOptions($this, '/index.php/v1/panopticon/akeebabackup/info'),
			CMSType::WORDPRESS => $this->getRequestOptions($this, '/v1/panopticon/akeebabackup/info'),
			default => [null, null]
		};

		if ($url === null)
		{
			// TODO Raise exception: unsupported site
		}

		$options[RequestOptions::HTTP_ERRORS] = false;

		try
		{
			$response = $client->get($url, $options);
		}
		catch (GuzzleException $e)
		{
			$throwThis ??= $e;
		}
		finally
		{
			$bodyContent = $response?->getBody()?->getContents();
		}

		$refreshResponse = (object) [
			'statusCode'   => $response?->getStatusCode(),
			'reasonPhrase' => $response?->getReasonPhrase(),
			'body'         => $this->sanitizeJson($bodyContent ?? ''),
		];

		try
		{
			$results = @json_decode($refreshResponse->body ?? '{}', flags: JSON_THROW_ON_ERROR);
		}
		catch (Throwable)
		{
			$results = null;

			$throwThis ??= new AkeebaBackupInvalidBody();
		}

		if (method_exists($this, 'updateDebugInfoInSession'))
		{
			$this->updateDebugInfoInSession(
				$response ?? null, $bodyContent, $throwThis, 'testconnection.akeebabackup.'
			);
		}

		// Do I have updated information?
		$config      = $this->getConfig();
		$info        = match ($this->cmsType()) {
			CMSType::JOOMLA => $results?->data?->attributes ?? null,
			CMSType::WORDPRESS => $results ?? null,
			default => null
		};
		$currentInfo = $config->get('akeebabackup.info') ?: new stdClass();
		$dirtyFlag   = false;

		$hasUpdatedInfo = array_reduce(
			['installed', 'version', 'api', 'secret', 'endpoints'],
			function (bool $carry, $key) use ($info, $currentInfo) {
				if ($carry)
				{
					return true;
				}

				$current = $currentInfo?->{$key} ?? null;
				$new     = $info?->{$key} ?? null;

				if (is_array($current))
				{
					$current = (object) $current;
				}

				if (is_array($new))
				{
					$new = (object) $new;
				}

				return $current != $new;
			},
			false
		);

		if ($hasUpdatedInfo || empty($info))
		{
			$config->set('akeebabackup.info', $info);
			$config->set('akeebabackup.lastRefreshResponse', $refreshResponse);

			$dirtyFlag = true;
		}

		if (is_array($results?->errors ?? null))
		{
			$firstError = reset($results->errors);

			$throwThis ??= new \RuntimeException(
				$firstError->title ?? 'Unknown API error',
				$firstError->code ?? 500
			);
		}

		// If `installed` is not true we cannot proceed with auto-detection.
		if (($info?->installed ?? false) !== true)
		{
			$config->set('akeebabackup.endpoint', null);

			$dirtyFlag = true;

			$throwThis ??= new AkeebaBackupNotInstalled();
		}
		elseif ($withEndpoints)
		{
			// Find an endpoint for the Akeeba Backup JSON API
			$session->set('testconnection.akeebabackup.step', 'Find the most suitable Akeeba Backup JSON API endpoint');

			// Auto-detect best endpoint.
			$endpoints                = array_merge(
				$info?->endpoints?->v2 ?? [],
				$info?->endpoints?->v1 ?? []
			);
			$newEndpointConfiguration = null;

			if (empty($endpoints))
			{
				throw new AkeebaBackupIsNotPro();
			}

			foreach ($endpoints as $someEndpoint)
			{
				$options = new JsonApiOptions(
					[
						'capath' => defined('AKEEBA_CACERT_PEM') ? AKEEBA_CACERT_PEM
							: CaBundle::getBundledCaBundlePath(),
						'ua'     => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
						'host'   => $someEndpoint,
						'secret' => $info?->secret,
					]
				);

				$httpClient = new HttpClientGuzzle($options);
				$apiClient  = new Connector($httpClient);
				$foundConfig = false;

				try
				{
					$apiClient->information();
					$foundConfig = true;
				}
				catch (Exception $e)
				{
					// Nothing
				}

				if (!$foundConfig)
				{
					try
					{
						$apiClient->autodetect();
					}
					catch (Throwable)
					{
						continue;
					}
				}

				$newEndpointConfiguration = (object) $httpClient->getOptions()->toArray();

				if (isset($newEndpointConfiguration->capath))
				{
					unset($newEndpointConfiguration->capath);
				}

				if (isset($newEndpointConfiguration->logger))
				{
					unset($newEndpointConfiguration->logger);
				}

				break;
			}

			if ($newEndpointConfiguration === null && $throw)
			{
				$throwThis ??= new AkeebaBackupNoEndpoint();
			}

			$oldEndpointConfiguration = $config->get('akeebabackup.endpoint');

			if ($oldEndpointConfiguration != $newEndpointConfiguration)
			{
				$config->set('akeebabackup.endpoint', $newEndpointConfiguration);

				$dirtyFlag = true;
			}

			if (method_exists($this, 'updateDebugInfoInSession'))
			{
				$this->updateDebugInfoInSession(
					$response ?? null, $bodyContent, $throwThis, 'testconnection.akeebabackup.'
				);
			}
		}

		// Commit any detected changes to the site object
		if ($dirtyFlag)
		{
			$this->setFieldValue('config', $config->toString());
		}

		if ($throw && !empty($throwThis))
		{
			throw $throwThis;
		}

		return $dirtyFlag;
	}

	/**
	 * Is the Akeeba Backup package or component installed on this site?
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function hasAkeebaBackup(bool $onlyProfessional = false): bool
	{
		if ($this->cmsType() === CMSType::JOOMLA)
		{
			// Joomla 3 doesn't support Download Keys, so we test the package name instead.
			if (version_compare($this->getConfig()->get('core.current.version', '4.0.0'), '3.99999.99999', 'le'))
			{
				return array_reduce(
					(array) $this->getConfig()->get('extensions.list'),
					fn(bool $carry, object $item) => $carry ||
					                                 (
						                                 $item->type === 'package'
						                                 && in_array($item->element, ['pkg_akeebabackup', 'pkg_akeeba', 'com_akeebabackup', 'com_akeeba'])
						                                 && (!$onlyProfessional || str_contains(strtolower($item->description), 'professional'))
					                                 ),
					false
				);
			}

			// Joomla 4 and later, we just check if the package supports download keys.
			return array_reduce(
				(array) $this->getConfig()->get('extensions.list'),
				fn(bool $carry, object $item) => $carry ||
				                                 (
					                                 $item->type === 'package'
					                                 && in_array($item->element, ['pkg_akeebabackup', 'pkg_akeeba'])
					                                 && (!$onlyProfessional || $item->downloadkey?->supported)
				                                 ),
				false
			);
		}

		if ($this->cmsType() === CMSType::WORDPRESS)
		{
			return array_reduce(
				(array) $this->getConfig()->get('extensions.list'),
				fn(bool $carry, object $item) => $carry ||
				                                 (
					                                 $item->type === 'plugin'
					                                 && $item->element === 'akeebabackupwp.php'
					                                 && (!$onlyProfessional || str_contains(strtolower($item->name), 'professional'))
				                                 ),
				false
			);
		}

		return false;
	}

	/**
	 * Get the information of the remote Akeeba Backup installation for debugging purposes.
	 *
	 * @return  array
	 * @since   1.0.6
	 */
	public function akeebaBackupGetInfoForDebug(): array
	{
		$logger    = new MemoryLogger();
		$connector = $this->getAkeebaBackupAPIConnector($logger);

		try
		{
			return (array) $connector->information();
		}
		catch (Exception $e)
		{
			return [
				'exception' => $e,
				'log'       => $logger->getItems(),
			];
		}
	}

	/**
	 * Get a list of backup records.
	 *
	 * Each returned object has the following keys:
	 * - id
	 * - description
	 * - comment
	 * - backupstart
	 * - backupend
	 * - status
	 * - origin
	 * - type
	 * - profile_id
	 * - archivename
	 * - absolute_path
	 * - multipart
	 * - tag
	 * - backupid
	 * - filesexist
	 * - remote_filename
	 * - total_size
	 * - frozen
	 * - instep
	 * - meta
	 * - hasRemoteFiles
	 *
	 * @param   bool  $cache  Should I use a cache to speed things up?
	 * @param   int   $from   Skip this many records
	 * @param   int   $limit  Maximum number of records to display
	 *
	 * @return  object[]
	 * @throws  CacheException
	 * @throws  InvalidArgumentException
	 * @since   1.0.0
	 */
	public function akeebaBackupGetBackups(bool $cache = true, int $from = 0, int $limit = 200, bool $skipConnectionCheck = false): array
	{
		if (!$skipConnectionCheck)
		{
			$this->ensureAkeebaBackupConnectionOptions();
		}

		return $this->getAkeebaBackupCacheController()->get(
			fn(Connector $connector, $from, $limit): array => $connector->getBackups($from, $limit),
			[
				$this->getAkeebaBackupAPIConnector(),
				$from,
				$limit,
			],
			sprintf('backupList-%d-%d-%d', $this->id, $from, $limit),
			$cache ? null : 0
		);
	}

	/**
	 * Retrieve a list of backup profiles
	 *
	 * @param   bool  $cache  Should I use a cache to speed things up?
	 *
	 * @return  array
	 * @throws  CacheException
	 * @throws  InvalidArgumentException
	 * @since   1.0.0
	 */
	public function akeebaBackupGetProfiles(bool $cache = true): array
	{
		$this->ensureAkeebaBackupConnectionOptions();

		return $this->getAkeebaBackupCacheController()->get(
			fn(Connector $connector): array => $connector->getProfiles(),
			[
				$this->getAkeebaBackupAPIConnector(),
			],
			sprintf(sprintf('profilesList-%d', $this->id)),
			$cache ? null : 0
		);
	}

	/**
	 * Starts taking a new backup.
	 *
	 * @param   int          $profile      The profile ID to use
	 * @param   string|null  $description  Backup description
	 * @param   string|null  $comment      Backup comment
	 *
	 * @return  object
	 * @throws  Throwable
	 * @since   1.0.0
	 */
	public function akeebaBackupStartBackup(int $profile = 1, ?string $description = null, ?string $comment = null
	): object
	{
		$this->ensureAkeebaBackupConnectionOptions();

		$httpClient = $this->getAkeebaBackupAPIClient();

		$data = $httpClient->doQuery(
			'startBackup', [
				'profile'     => (int) $profile,
				'description' => $description ?: 'Remote backup',
				'comment'     => $comment,
			]
		);

		$info = $this->akeebaBackupHandleAPIResponse($data);

		$info->data = $data;

		return $info;
	}

	/**
	 * Continues taking a backup.
	 *
	 * @param   string|null  $backupId  The backup ID to continue stepping through.
	 *
	 * @return  object
	 * @throws  Throwable
	 * @since   1.0.0
	 */
	public function akeebaBackupStepBackup(?string $backupId): object
	{
		$this->ensureAkeebaBackupConnectionOptions();

		$httpClient = $this->getAkeebaBackupAPIClient();
		$parameters = [];

		if (!empty($backupId))
		{
			$parameters['backupid'] = $backupId;
		}

		$data = $httpClient->doQuery('stepBackup', $parameters);
		$info = $this->akeebaBackupHandleAPIResponse($data);

		$info->data = $data;

		return $info;
	}

	/**
	 * Delete a backup record.
	 *
	 * @param   int  $id  The backup record to delete
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function akeebaBackupDelete(int $id): void
	{
		$this->ensureAkeebaBackupConnectionOptions();

		$connector = $this->getAkeebaBackupAPIConnector();

		$connector->delete($id);
	}

	/**
	 * Delete a backup record's files from the web server.
	 *
	 * @param   int  $id  The backup record whose files will be deleted
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function akeebaBackupDeleteFiles(int $id): void
	{
		$this->ensureAkeebaBackupConnectionOptions();

		$connector = $this->getAkeebaBackupAPIConnector();

		$connector->deleteFiles($id);
	}

	public function akeebaBackupGetAllScheduledTasks(): Collection
	{
		return $this->getSiteSpecificTasks('akeebabackup');
	}

	public function akeebaBackupGetEnqueuedTasks(): Collection
	{
		return $this->getSiteSpecificTasks('akeebabackup')
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
					if (empty($params->get('enqueued_backup')))
					{
						return false;
					}

					// Its next execution date must be empty or in the past
					if (empty($task->last_execution))
					{
						return true;
					}

					$date = $this->container->dateFactory($task->last_execution, 'UTC');
					$now  = $this->container->dateFactory();

					return ($date < $now);
				}
			);
	}

	/**
	 * Enqueue a new backup
	 *
	 * @param   int          $profile
	 * @param   string|null  $description
	 * @param   string|null  $comment
	 *
	 * @return  void
	 */
	public function akeebaBackupEnqueue(
		int $profile = 1, ?string $description = null, ?string $comment = null, ?User $user = null
	): void
	{
		// Try to find an akeebabackup task object which is run once, not running / initial schedule, and matches the specifics
		$tasks = $this->akeebaBackupGetEnqueuedTasks();

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
				'type'            => 'akeebabackup',
				'params'          => json_encode(
					[
						'run_once'        => 'disable',
						'enqueued_backup' => 1,
						'profile_id'      => $profile,
						'description'     => $description,
						'comment'         => $comment ?? '',
						'initiatingUser'  => $user?->getId(),
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

	/**
	 * Ensures that we have valid Akeeba Backup Endpoint options
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function ensureAkeebaBackupConnectionOptions(): void
	{
		$config          = $this->getConfig();
		$info            = $config->get('akeebabackup.info');
		$endpointOptions = $config->get('akeebabackup.endpoint');

		if (empty($info) || (!empty($info?->api) && empty($endpointOptions)))
		{
			$this->getDbo()->lockTable('#__sites');
			$this->find($this->getId());

			try
			{
				$this->saveSite(
					$this,
					function (Site $model)
					{
						$dirty = $model->testAkeebaBackupConnection(true);

						if (!$dirty)
						{
							// This short-circuits saveSite(), telling it to save nothing.
							throw new \RuntimeException('Nothing to save');
						}
					},
					function (Throwable $e)
					{
						if (!$e instanceof \RuntimeException || $e->getMessage() !== 'Nothing to save')
						{
							throw $e;
						}
					}
				);

				$info            = $config->get('akeebabackup.info');
				$endpointOptions = $config->get('akeebabackup.endpoint');
			}
			catch (GuzzleException $e)
			{
				throw new AkeebaBackupNoInfoException(previous: $e);
			}
			finally
			{
				$this->getDbo()->unlockTables();
			}
		}

		if (empty($info) || ($info?->api ?? null) === null)
		{
			throw new AkeebaBackupNoInfoException();
		}

		if (empty($info?->api))
		{
			throw new AkeebaBackupIsNotPro();
		}

		if (empty($endpointOptions))
		{
			throw new AkeebaBackupCannotConnectException();
		}
	}

	/**
	 * Get the cache controller for requests to Akeeba Backup
	 *
	 * @return  CallbackController
	 * @since   1.0.0
	 */
	private function getAkeebaBackupCacheController(): CallbackController
	{
		if (empty($this->callbackControllerForAkeebaBackup))
		{
			/** @var Container $container */
			$container = $this->container;
			$pool      = $container->cacheFactory->pool('akeebabackup');

			$this->callbackControllerForAkeebaBackup = new CallbackController($container, $pool);
		}

		return $this->callbackControllerForAkeebaBackup;
	}

	/**
	 * Get the Akeeba Backup JSON API Connector object
	 *
	 * @return  Connector
	 * @since   1.0.0
	 */
	private function getAkeebaBackupAPIConnector(?LoggerInterface $logger = null): Connector
	{
		return new Connector($this->getAkeebaBackupAPIClient($logger));
	}

	/**
	 * Get the Akeeba Backup JSON API HTTP client
	 *
	 * @return  HttpClientInterface
	 */
	private function getAkeebaBackupAPIClient(?LoggerInterface $logger = null): HttpClientInterface
	{
		$config            = $this->getConfig();
		$connectionOptions = (array) $config->get('akeebabackup.endpoint', null);

		if (empty($connectionOptions))
		{
			// This should never happen; we've already run ensureAkeebaBackupConnectionOptions to prevent this problem.
			throw new AkeebaBackupCannotConnectException();
		}

		$connectionOptions['capath'] = defined('AKEEBA_CACERT_PEM') ? AKEEBA_CACERT_PEM : null;

		if ($logger)
		{
			$connectionOptions['logger'] = $logger;
		}

		$options = new JsonApiOptions($connectionOptions);

		return new HttpClientGuzzle($options);
	}

	private function akeebaBackupHandleAPIResponse(object $data): object
	{
		$backupID       = null;
		$backupRecordID = 0;
		$archive        = '';

		if ($data->body?->status != 200)
		{
			throw new RemoteError('Error ' . $data->body->status . ": " . $data->body->data);
		}

		if (isset($data->body->data->BackupID))
		{
			$backupRecordID = $data->body->data->BackupID;
		}

		if (isset($data->body->data->backupid))
		{
			$backupID = $data->body->data->backupid;
		}

		if (isset($data->body->data->Archive))
		{
			$archive = $data->body->data->Archive;
		}

		return (object) [
			'backupID'       => $backupID,
			'backupRecordID' => $backupRecordID,
			'archive'        => $archive,
		];
	}
}