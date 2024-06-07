<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\LanguageListTrait;
use Akeeba\Panopticon\Library\Aes\Ctr;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Registry\Registry;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use LogicException;
use RuntimeException;
use Throwable;

#[AsTask(
	name: 'joomlaupdate',
	description: 'PANOPTICON_TASKTYPE_JOOMLAUPDATE'
)]
class JoomlaUpdate extends AbstractCallback
{
	use ApiRequestTrait;
	use SiteNotificationEmailTrait;
	use TimeAgoTrait;
	use EmailSendingTrait;
	use LanguageListTrait;
	use JsonSanitizerTrait;

	protected string $currentState;

	public function __invoke(object $task, Registry $storage): int
	{
		$this->currentState = $storage->get('fsm.state', 'init');
		$site               = $this->getSite($task);
		$config             = $site->getConfig();

		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		try
		{
			if ($site->cmsType() !== CMSType::JOOMLA)
			{
				throw new RuntimeException('This is not a Joomla site!');
			}

			switch ($this->currentState)
			{
				case 'init':
				default:
					$this->runInit($task, $storage);
					break;

				case 'download':
					$this->runDownload($task, $storage);
					break;

				case 'beforeEvents':
					$this->runBeforeEvents($task, $storage);
					break;

				case 'backup':
					$this->runBackup($task, $storage);
					break;

				case 'enable':
					$this->runEnable($task, $storage);
					break;

				case 'extract':
					$this->runExtract($task, $storage);
					break;

				case 'postExtract':
					$this->runPostExtract($task, $storage);
					break;

				case 'finalise':
					$this->runFinalise($task, $storage);
					break;

				case 'reloadUpdates':
					$this->runReloadUpdates($task, $storage);
					break;

				case 'siteInfo':
					// I have to wrap this in a transaction for saving the new information to work.
					$this->container->db->transactionStart();
					$this->runSiteInfo($task, $storage);
					$this->container->db->transactionCommit();
					break;

				case 'afterEvents':
					$this->runAfterEvents($task, $storage);
					break;

				case 'email':
					$this->sendSuccessEmail($task, $storage);
					break;

				case 'finish':
					break;
			}
		}
		catch (Throwable $e)
		{
			// Log the exception
			$this->logger->critical(
				$e->getMessage(), [
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				]
			);

			// Log the failed update
			try
			{
				$params         = (($task->params ?? null) instanceof Registry) ?
					($task->params ?? null) : new Registry($task->params ?? null);
				$backupOnUpdate = $config->get('config.core_update.backup_on_update', 0);
				$backupProfile  = $config->get('config.core_update.backup_profile', 1);

				$report = Reports::fromCoreUpdateInstalled(
					$site->id,
					$storage->get('oldVersion', null),
					$storage->get('newVersion', null),
					false,
					$e
				);

				$context = $report->context;
				$context->set('start_time', $storage->get('start_timestamp', null));
				$context->set('end_time', time());
				$context->set('failed_step', $this->currentState);
				$context->set('backup_on_update', (bool) $backupOnUpdate);
				$context->set('backup_profile', $backupOnUpdate ? $backupProfile : null);

				$report->save(
					[
						'context'    => $context,
						'created_by' => $params->get('initiatingUser', 0),
					]
				);
			}
			catch (Throwable)
			{
				// Ignore this
			}

			// Send email about the failed update
			if ($storage->get('email_error', true))
			{
				$this->sendEmail(
					'joomlaupdate_failed', $storage, ['panopticon.super', 'panopticon.manage'], [
						'MESSAGE' => $e->getMessage(),
					]
				);
			}

			// Rethrow the exception so that the task gets the "knocked out" state
			throw $e;
		}

		// If we are in a state other than "finish" we have more work to do.
		if ($this->currentState !== 'finish')
		{
			$storage->set('fsm.state', $this->currentState);

			return Status::WILL_RESUME->value;
		}

		// Log a successful update
		try
		{
			$params = (($task->params ?? null) instanceof Registry) ?
				($task->params ?? null) : new Registry($task->params ?? null);

			$report = Reports::fromCoreUpdateInstalled(
				$site->id,
				$storage->get('oldVersion', null),
				$storage->get('newVersion', null),
				true
			);

			$context        = $report->context;
			$backupOnUpdate = $config->get('config.core_update.backup_on_update', 0);
			$backupProfile  = $config->get('config.core_update.backup_profile', 1);
			$context->set('start_time', $storage->get('start_timestamp', null));
			$context->set('end_time', time());
			$context->set('backup_on_update', (bool) $backupOnUpdate);
			$context->set('backup_profile', $backupOnUpdate ? $backupProfile : null);

			$report->save(
				[
					'context'    => $context,
					'created_by' => $params->get('initiatingUser', 0),
				]
			);

		}
		catch (Throwable $e)
		{
			// Ignore this
		}


		// This is the "finish" state. We are done.
		return Status::OK->value;
	}

	/**
	 * Implements a simplistic finite-state machine.
	 *
	 * The states are:
	 * - init: Initial state, performs sanity self-checks
	 * - download: Downloads the update package
	 * - beforeEvents: Executes any events which need to precede the update
	 * - enable: Enables the restore.php / extract.php file which extracts the update
	 * - extract: Performs the archive extraction
	 * - postExtract: Executes the finalisation code in restore.php / extract.php itself (file cleanup)
	 * - finalize: Executes the Joomla Update model's finalisation (database tasks, ...)
	 * - afterEvents: Executes any events which need to run after the update
	 * - email: Send the success email
	 * - finish: Signals the need to return successfully
	 *
	 * @return  void
	 */
	protected function advanceState(): void
	{
		$this->currentState = match ($this->currentState)
		{
			default => 'download',
			'download' => 'beforeEvents',
			'beforeEvents' => 'backup',
			'backup' => 'enable',
			'enable' => 'extract',
			'extract' => 'postExtract',
			'postExtract' => 'finalise',
			'finalise' => 'reloadUpdates',
			'reloadUpdates' => 'siteInfo',
			'siteInfo' => 'afterEvents',
			'afterEvents' => 'email',
			'email' => 'finish',
		};
	}

	/**
	 * Runs the events and returns a singular result.
	 *
	 * Event handlers are responsible for executing themselves one after the other. This is done with the following
	 * mechanism.
	 *
	 * Let's say $event="onFoobar" and the event handler assigns itself the internal name "com.example.test".
	 *
	 * The event handler gets the following variables:
	 * $amIDone = $storage->get("onFoobar.com.example.test", false);
	 * $activeHandler = $storage->get("onFoobar.activeHandler");
	 *
	 * If $amIDone === true the handler returns Status::OK->value.
	 *
	 * If $activeHandler is not empty AND is not 'com.example.test' the handler returns
	 * Status::INITIAL_SCHEDULE->value.
	 *
	 * In any other case, the handler sets itself as the active handler:
	 * $activeHandler = $storage->get("onFoobar.activeHandler", 'com.example.test');
	 *
	 * It will then execute some work.
	 *
	 * If the work is complete it will do:
	 * $activeHandler = $storage->get("onFoobar.activeHandler", null);
	 * $storage->get("onFoobar.com.example.test", true);
	 * and returns Status::OK->value.
	 *
	 * No other return values are considered valid.
	 *
	 * @param   string    $event
	 * @param   object    $task
	 * @param   Registry  $storage
	 *
	 * @return  bool True if all handlers have executed successfully.
	 */
	protected function runEvent(string $event, object $task, Registry $storage): bool
	{
		$results = $this->container->eventDispatcher->trigger($event, [$task, $storage]);

		// No handlers executed. We are done!
		if (empty($results))
		{
			return true;
		}

		// We are done if all values are Status::OK, or they are unexpected.
		return array_reduce(
			$results,
			fn(bool $carry, $result) => $carry
			                            && (
				                            $result === Status::OK->value
				                            || !in_array(
					                            $result, [Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
				                            )
			                            ),
			true
		);
	}

	/**
	 * Initialise the update
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 */
	private function runInit(object $task, Registry $storage): void
	{
		// Initialise the email variables
		$storage->set(
			'email_variables', [
				'NEW_VERSION' => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_VERSION'),
				'OLD_VERSION' => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_VERSION'),
				'SITE_NAME'   => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_SITE'),
				'SITE_URL'    => 'https://www.example.com',
			]
		);

		// Remember when we started installing the update
		$storage->set('start_timestamp', time());

		// Try to get the site
		$site = $this->getSite($task);

		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Is the site enabled?
		if (!$site->enabled)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf('PANOPTICON_TASK_JOOMLAUPDATE_ERR_SITE_DISABLED', $task->site_id)
			);
		}

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_PREPARING',
				$site->id,
				$site->name
			)
		);

		// Does this site actually have an update available?
		$config = $site->getFieldValue('config', '{}');
		$config = ($config instanceof Registry) ? $config->toString() : $config;
		try
		{
			$config = @json_decode($config);
		}
		catch (Exception $e)
		{
			$config = null;
		}

		$currentVersion = $config?->core?->current?->version;
		$latestVersion  = $config?->core?->latest?->version;
		$params         = (($task->params ?? null) instanceof Registry) ?
			($task->params ?? null) : new Registry($task->params ?? null);
		$force          = $params->get('force', false);

		$storage->set('oldVersion', $currentVersion);
		$storage->set('newVersion', $latestVersion);

		if (
			!$force && !empty($currentVersion) && !empty($latestVersion)
			&& version_compare($currentVersion, $latestVersion, 'ge')
		)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_NO_UPDATE_AVAILABLE', $site->id,
					$site->name, $currentVersion, $latestVersion
				)
			);
		}
		elseif (!empty($currentVersion) && !empty($latestVersion))
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_WILL_BE_UPDATED',
					$site->id,
					$site->name,
					$currentVersion,
					$latestVersion
				)
			);
		}

		$storage->set(
			'email_variables', [
				'NEW_VERSION' => $latestVersion,
				'OLD_VERSION' => $currentVersion,
				'SITE_NAME'   => $site->name,
				'SITE_URL'    => $site->getBaseUrl(),
			]
		);
		$storage->set('site_id', $site->id);

		$cc = $this->getSiteNotificationEmails($config);

		$storage->set('email_cc', $cc);

		$storage->set('email_after', (bool) ($config?->config?->core_update?->email_after ?? true));
		$storage->set('email_error', (bool) ($config?->config?->core_update?->email_error ?? true));


		// Finally, advance the state
		$this->advanceState();
	}

	/**
	 * Get the site object corresponding to the task we are currently handling
	 *
	 * @param   object  $task
	 *
	 * @return  Site
	 */
	private function getSite(object $task): Site
	{
		// Do we have a site?
		if ($task->site_id <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_SITE_INVALIDID'));
		}

		// Does the site exist?
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->find($task->site_id);

		if ($site->id != $task->site_id)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_SITE_NOT_EXIST',
					$task->site_id
				)
			);
		}

		return $site;
	}

	/**
	 * Download the update to the remote site
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function runDownload(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		// Retrieve information from the storage
		$mode = $storage->get('update.mode', 'chunk');

		$storage->set('update.mode', $mode);

		switch ($mode)
		{
			case 'chunk':
			default:
				// Get values from the storage
				$url         = $storage->get('update.package_url', null);
				$size        = $storage->get('update.size', null);
				$offset      = $storage->get('update.offset', null);
				$chunk_index = $storage->get('update.chunk_index', null);
				$max_time    = $storage->get('update.max_time', null);

				// Sanitise values
				$url         = empty($url) ? null : (filter_var($url, FILTER_SANITIZE_URL) ?: null);
				$size        = $size >= 0 ? $size : null;
				$offset      = $offset >= 0 ? $offset : null;
				$chunk_index = $chunk_index >= 0 ? $chunk_index : null;
				$max_time    = $max_time >= 0 ? $max_time : null;

				$offsetInt = (int) ($offset ?? -1);
				$offsetInt = $offsetInt <= 0 ? 0 : $offset;

				$this->logger->info(
					$offsetInt <= 0
						? $this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING_CHUNK_START',
						$site->id,
						$site->name
					)
						: $this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING_CHUNK_CONTINUE',
						$site->id,
						$site->name,
						$offsetInt
					)
				);

				$httpClient = $this->container->httpFactory->makeClient(cache: false);

				// Force reload the update information (in case the latest available Joomla version changed)
				if ($offsetInt <= 0)
				{
					[$serviceUrl, $options] = $this->getRequestOptions(
						$site, '/index.php/v1/panopticon/core/update?force=1'
					);
					$response = $httpClient->get($serviceUrl, $options);

					if ($response->getStatusCode() !== 200)
					{
						throw new RuntimeException(
							$this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_RELOAD_UPDATES_FAILED')
						);
					}
				}

				// Then, download the update
				$postData = [];

				if (!empty($url))
				{
					$postData['url'] = $url;
				}

				if (!empty($size))
				{
					$postData['size'] = $size;
				}

				if (!empty($offset))
				{
					$postData['offset'] = $offset;
				}

				if (!empty($chunk_index))
				{
					$postData['chunk_index'] = $chunk_index;
				}

				if (!empty($max_time))
				{
					$postData['max_time'] = $max_time;
				}

				[
					$serviceUrl,
					$options,
				] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update/download/chunked');

				if (!empty($postData))
				{
					$options[RequestOptions::FORM_PARAMS] = $postData;
				}

				// We expect this to fail on Joomla 3 where the connector does not support chunked downloads.
				try
				{
					$this->logger->debug('Sending request for chunked download', $postData);
					$response = $httpClient->post($serviceUrl, $options);
				}
				catch (GuzzleException $e)
				{
					$this->logger->notice(
						$this->getLanguage()->sprintf(
							'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING_CHUNK_FAILED',
							$site->id,
							$site->name
						),
						[$e->getMessage()]
					);

					$storage->set('update.mode', 'single');

					return;
				}

				$json = $this->sanitizeJson($response->getBody()->getContents());

				try
				{
					$raw = @json_decode($json);
				}
				catch (Exception $e)
				{
					$raw = null;
				}

				if (empty($raw) || empty($raw->data?->attributes?->basename))
				{
					$this->logger->notice(
						$this->getLanguage()->sprintf(
							'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING_CHUNK_FAILED',
							$site->id,
							$site->name
						),
						[empty($raw) ? $json : $raw]
					);

					$storage->set('update.mode', 'single');

					return;
				}

				$storage->set('update.basename', $raw->data?->attributes?->basename);
				$storage->set('update.package_url', $raw->data?->attributes?->url);
				$storage->set('update.size', $raw->data?->attributes?->size);
				$storage->set('update.offset', $raw->data?->attributes?->offset);
				$storage->set('update.chunk_index', $raw->data?->attributes?->chunk_index);

				$error = $raw->data?->attributes?->error;
				$done  = $raw->data?->attributes?->done;

				if (!empty($error))
				{
					$this->logger->notice($error);
					$this->logger->notice(
						$this->getLanguage()->sprintf(
							'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING_CHUNK_FAILED',
							$site->id,
							$site->name
						)
					);

					$storage->set('update.mode', 'single');

					return;
				}

				if ($done)
				{
					$this->logger->debug('Done with the download');

					$this->advanceState();
				}

				$this->logger->debug('Will continue download in the next step');

				break;

			case 'single':
				$this->logger->info('Downloading the update (single part)');

				$this->doSinglePartDownload($site, $storage);

				$this->logger->info('Downloaded the update (single part)');

				$this->advanceState();
				break;
		}
	}

	/**
	 * Executes the event which take place BEFORE the update itself
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 */
	private function runBeforeEvents(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_PREUPDATE_EVENTS',
				$site->id,
				$site->name
			)
		);

		if (!$this->runEvent('onBeforeJoomlaUpdate', $task, $storage))
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_PREUPDATE_WILL_CONTINUE',
					$site->id,
					$site->name
				)
			);
		}

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_PREUPDATE_FINISHED',
				$site->id,
				$site->name
			)
		);

		$this->advanceState();
	}

	private function runBackup(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		// Collect configuration and task process information
		$config         = $site->getConfig();
		$backupOnUpdate = $config->get('config.core_update.backup_on_update', 0);
		$backupProfile  = $config->get('config.core_update.backup_profile', 1);
		$lastStatus     = $storage->get('backup.returnStatus', Status::INITIAL_SCHEDULE->value);
		$subTaskStorage = $storage->get('backup.subTaskStorage', null);

		// Has the backup already finished?
		if (!in_array($lastStatus, [Status::OK->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]))
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_DONE',
					$site->id,
					$site->name
				)
			);

			$this->advanceState();

			return;
		}

		if (!$backupOnUpdate)
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_NOT',
					$site->id,
					$site->name
				)
			);

			$this->advanceState();

			return;
		}

		if ($lastStatus === Status::WILL_RESUME->value)
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_RESUME',
					$site->id,
					$site->name
				)
			);
		}
		else
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_START',
					$site->id,
					$site->name
				)
			);
		}

		// Run a chunk of the backup task.
		$callback = $this->container->taskRegistry->get('akeebabackup');

		$callback->setLogger($this->logger);

		$taskStorage = (new Registry())->loadString($subTaskStorage ?: '{}');
		$dummyTask   = (object) [
			'site_id' => $site->id,
			'params'  => (new Registry())->loadArray(
				[
					'profile_id'  => $backupProfile,
					'description' => $this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_DESCRIPTION',
						$config->get(
							'core.latest.version',
							$config->get(
								'core.current.version',
								$this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_VERSION')
							)
						)
					),
				]
			),
			'storage' => $taskStorage,
		];

		$return = $callback($dummyTask, $taskStorage);

		$storage->set('backup.returnStatus', $return);
		$storage->set('backup.subTaskStorage', $taskStorage->toString());

		// Has the backup finished successfully?
		if ($return === Status::OK->value)
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_DONE',
					$site->id,
					$site->name
				)
			);

			$this->advanceState();

			return;
		}

		// Do we have to come back for more?
		if ($return === Status::WILL_RESUME->value)
		{
			return;
		}

		// If I am here, the backup has failed; throw an exception, so we can cancel the update
		throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LOG_BACKUP_FAIL'));
	}

	/**
	 * Enable the Joomla Update extraction script
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function runEnable(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_ENABLE_EXTRACT',
				$site->id,
				$site->name
			)
		);

		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update/activate');
		$response = $httpClient->post($url, $options);
		$json     = $this->sanitizeJson($response->getBody()->getContents());

		try
		{
			$raw = @json_decode($json);
		}
		catch (Exception $e)
		{
			$raw = null;
		}

		if (empty($raw) || empty($raw->data?->attributes?->password))
		{
			$this->logger->debug(json_encode($raw));

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_ENABLE_FAILED'));
		}

		$baseName = $raw->data?->attributes?->file;
		$password = $raw->data?->attributes?->password;
		$filesize = $raw->data?->attributes?->filesize ?: 0;

		if (basename($baseName) != $storage->get('update.basename') || $filesize <= 0)
		{
			$this->logger->debug(json_encode($raw));

			throw new RuntimeException(
				$this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_UPDATE_DISAPPEARED')
			);
		}

		if (empty($password))
		{
			$this->logger->debug(json_encode($raw));

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NO_PASSWORD'));
		}

		$storage->set('update.password', $password);
		$storage->set('update.filesize', $filesize);

		$this->advanceState();
	}

	/**
	 * Extracts the update package using Joomla Update's restore.php or extract.php
	 *
	 * The restore.php file is used by Joomla 4.0.0–4.0.3 inclusive. It is, in fact, a very old version of Akeeba
	 * Restore we retired in 2016.
	 *
	 * The extract.php file is used by Joomla 4.0.4 and later. It is a rewritten and refactored version of the
	 * extraction script which I contributed to Joomla: https://github.com/joomla/joomla-cms/pull/35388
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function runExtract(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$step = $storage->get('restore.step', 'start');
		$url  = $this->getExtractUrl($site);

		if (str_ends_with($url, 'restore.php'))
		{
			if ($step == 'start')
			{
				$this->logger->info(
					$this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_EXTRACT_START',
						$site->id,
						$site->name
					)
				);

				$this->j40ExtractStart($site, $storage);

				$done = false;

				$storage->set('restore.step', 'step');
			}
			else
			{
				$this->logger->info(
					$this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_EXTRACT_CONTINUE',
						$site->id,
						$site->name
					)
				);

				$done = $this->j40ExtractStep($site, $storage);
			}
		}
		else
		{
			if ($step == 'start')
			{
				$this->logger->info(
					$this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_EXTRACT_START',
						$site->id,
						$site->name
					)
				);

				$done = $this->j404ExtractStart($site, $storage);

				$storage->set('restore.step', 'step');
			}
			else
			{
				$this->logger->info(
					$this->getLanguage()->sprintf(
						'PANOPTICON_TASK_JOOMLAUPDATE_LOG_EXTRACT_CONTINUE',
						$site->id,
						$site->name
					)
				);

				$done = $this->j404ExtractStep($site, $storage);
			}
		}

		if ($done)
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_EXTRACT_FINISH',
					$site->id,
					$site->name
				)
			);

			$this->advanceState();
		}

	}

	/**
	 * Returns the URL to Joomla Update's restore.php (Joomla 4.0.0 to 4.0.3) or extract.php (Joomla 4.0.4 and later).
	 *
	 * @param   Site  $site  The site we're working on
	 *
	 * @return  string  The absolute URL to the restore.php or extract.php
	 */
	private function getExtractUrl(Site $site): string
	{
		$config = $site->config;
		$config = json_decode(($config instanceof Registry) ? $config->toString() : $config);

		$currentVersion = $config?->core?->current?->version ?? '4.0.4';
		$endpoint       = version_compare($currentVersion, '4.0.3', 'gt') ? 'extract.php' : 'restore.php';
		$url            = $site->getBaseUrl();

		return $url . '/administrator/components/com_joomlaupdate/' . $endpoint;
	}

	/**
	 * Starts the update extraction on Joomla 4.0.0–4.0.3
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function j40ExtractStart(Site $site, Registry $storage): void
	{
		// First, we do a ping
		$data = $this->doEncryptedAjax($site, $storage, ['task' => 'ping']);

		if (($data?->status ?? false) === false)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_EXTRACTION_FAILED',
					$data?->message ?? 'Unknown error'
				)
			);
		}

		// Then, we start the extraction
		$data = $this->doEncryptedAjax($site, $storage, ['task' => 'startRestore']);

		if (($data?->status ?? false) === false)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_EXTRACTION_FAILED',
					$data?->message ?? 'Unknown error'
				)
			);
		}

		$factory = $data?->factory ?? null;

		if ($factory === null)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NO_FACTORY'));
		}

		$storage->set('restore.factory', $factory);
	}

	/**
	 * Perform an encrypted POST request to Joomla Update's restore.php (Joomla 4.0.0 to 4.0.3 inclusive)
	 *
	 * @param   Site          $site     The site we're working on
	 * @param   Registry      $storage  The temporary storage of the task
	 * @param   array|object  $data     The data to POST to restore.php
	 *
	 * @return  mixed
	 * @throws  GuzzleException
	 */
	private function doEncryptedAjax(Site $site, Registry $storage, array|object $data): mixed
	{
		// Get the URL to the restore.php
		$url = $this->getExtractUrl($site);

		// If it's extract.php, not restore.php, we have done something wrong!
		if (!str_ends_with($url, '/restore.php'))
		{
			throw new LogicException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NOT_J40'));
		}

		// Encrypt the request data with AES-128-CTR
		$password = $storage->get('update.password', '');
		$json     = Ctr::AESEncryptCtr(json_encode($data), $password, 128);

		$postData = [
			'json' => $json,
		];

		[, $options] = $this->getRequestOptions($site, '/foobar');

		// Send the request
		$response = $this->container->httpFactory->makeClient(cache: false)
			->post(
				$url, array_merge(
					$options, [
						'form_params' => $postData,
					]
				)
			);

		// We must always get HTTP 200
		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_UNEXPECTED_HTTP',
					$response->getStatusCode()
				)
			);
		}

		// The response is enclosed in '###'. Make sure of that and extract the actual response.
		$json        = $this->sanitizeJson($response->getBody()->getContents());
		$firstHashes = strpos($json, '###');

		if ($firstHashes === false)
		{
			$this->logger->debug('Invalid JSON response:');
			$this->logger->debug($json);

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_INVALID_JSON'));
		}

		$json = substr($json, $firstHashes + 3);

		$secondHashes = strrpos($json, '###');

		if ($secondHashes === false)
		{
			$this->logger->debug('Invalid JSON response:');
			$this->logger->debug($json);

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_INVALID_JSON'));
		}

		$json = substr($json, 0, $secondHashes);

		// The result may be encrypted, therefore we may have to decode it.
		try
		{
			$raw = @json_decode($json);
		}
		catch (Exception $e)
		{
			$raw = null;
		}

		if ($raw === null)
		{
			$json = Ctr::AESDecryptCtr($json, $password, 128);

			try
			{
				$raw = @json_decode($json);
			}
			catch (Exception $e)
			{
				$raw = null;
			}
		}

		if ($raw === null)
		{
			$this->logger->debug('Invalid JSON response:');
			$this->logger->debug($json);

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_INVALID_JSON'));
		}

		return $raw;
	}

	/**
	 * Steps through the update extraction on Joomla 4.0.0–4.0.3
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  bool  True when the extraction is done; false otherwise.
	 * @throws  GuzzleException
	 */
	private function j40ExtractStep(Site $site, Registry $storage): bool
	{
		$factory = $storage->get('restore.factory', null);

		if ($factory === null)
		{
			throw new LogicException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_FACTORY_GONE'));
		}

		$data = [
			'task'    => 'stepRestore',
			'factory' => $factory,
		];

		$data = $this->doEncryptedAjax($site, $storage, $data);

		if (($data?->status ?? false) === false)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_EXTRACTION_FAILED',
					$data?->message ?? 'Unknown error'
				)
			);
		}

		$isDone  = $data?->done ?? false;
		$factory = $data?->factory ?? null;

		if (!$isDone && $factory === null)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NO_FACTORY'));
		}
		elseif ($factory !== null)
		{
			$storage->set('restore.factory', $factory);
		}

		// Update progress indicators
		$bytesIn  = $storage->get('progress.bytesIn', 0) + ($data?->bytesIn ?? 0);
		$bytesOut = $storage->get('progress.bytesOut', 0) + ($data?->bytesOut ?? 0);
		$files    = $storage->get('progress.files', 0) + ($data?->files ?? 0);
		$fileSize = $storage->get('update.filesize', 0);
		$percent  = ($fileSize > 0) ? (100 * ($bytesIn / $fileSize)) : 0;

		$storage->set('progress.bytesIn', $bytesIn);
		$storage->set('progress.bytesOut', $bytesOut);
		$storage->set('progress.files', $files);
		$storage->set('progress.percent', $percent);

		if ($isDone)
		{
			$storage->set('progress.percent', 100);

			return true;
		}

		return false;
	}

	/**
	 * Starts the update extraction on Joomla 4.0.4 or later
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  bool  True if we're done extracting, false otherwise
	 * @throws  GuzzleException
	 */
	private function j404ExtractStart(Site $site, Registry $storage): bool
	{
		$data = $this->doExtractAjax($site, $storage, ['task' => 'startExtract']);

		return $this->handleJ404ExtractResponse($data, $storage);
	}

	/**
	 * Perform a password-protected POST request to Joomla Update's extract.php (Joomla 4.0.4 and later)
	 *
	 * @param   Site          $site     The site we're working on
	 * @param   Registry      $storage  The temporary storage of the task
	 * @param   array|object  $data     The data to POST to restore.php
	 *
	 * @return  mixed
	 * @throws  GuzzleException
	 */
	private function doExtractAjax(Site $site, Registry $storage, array|object $data): mixed
	{
		// Get the URL to the extract.php
		$url = $this->getExtractUrl($site);

		// If it's restore.php, not extract.php, we have done something wrong!
		if (!str_ends_with($url, '/extract.php'))
		{
			throw new LogicException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NOT_J404'));
		}

		// Prepare the POST data
		$postData                = (array) $data;
		$postData['password']    = $storage->get('update.password', '');
		$postData['_randomJunk'] = sha1(random_bytes(32));

		// Prepare the client options
		$client                                         = $this->container->httpFactory->makeClient(cache: false);
		$options                                        = $this->container->httpFactory->getDefaultRequestOptions();
		$options[RequestOptions::HEADERS]               ??= [];
		$options[RequestOptions::HEADERS]['User-Agent'] = 'panopticon/' . AKEEBA_PANOPTICON_VERSION;

		// Administrator HTTP Authentication
		$config   = $site->getConfig();
		$username = $config->get('config.diaxeiristis_onoma');
		$password = $config->get('config.diaxeiristis_sunthimatiko');

		if (!empty($username))
		{
			$options[RequestOptions::AUTH] = [$username, $password];
		}

		$options[RequestOptions::HTTP_ERRORS] = false;

		// Send the request
		$response = $client
			->post(
				$url, array_merge(
					$options, [
						'form_params' => $postData,
					]
				)
			);

		// Special case: HTTP 401 means that the user failed to provide the administrator folder username and password.
		if ($response->getStatusCode() === 401)
		{
			throw new RuntimeException(
				$this->getLanguage()->text(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_HTTP_401',
				)
			);
		}

		// We must always get HTTP 200
		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_UNEXPECTED_HTTP',
					$response->getStatusCode()
				)
			);
		}

		// Get the JSON response and decode it
		$json = $this->sanitizeJson($response->getBody()->getContents());

		try
		{
			$raw = @json_decode($json);
		}
		catch (Exception $e)
		{
			$raw = null;
		}

		if ($raw === null)
		{
			$this->logger->debug('Invalid JSON response:');
			$this->logger->debug($json);

			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_INVALID_JSON'));
		}

		return $raw;
	}

	/**
	 * Handles the Joomla Update's extract.php response to an extraction step
	 *
	 * @param   mixed     $data     The response data (decoded from JSON)
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  bool  True if we're done extracting, false otherwise
	 */
	private function handleJ404ExtractResponse(mixed $data, Registry $storage): bool
	{
		if (($data?->status ?? false) === false)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_EXTRACTION_FAILED',
					$data?->message ?? 'Unknown error'
				)
			);
		}

		$isDone  = $data?->done ?? false;
		$factory = $data?->instance ?? null;

		if (!$isDone && $factory === null)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_NO_FACTORY'));
		}
		elseif ($factory !== null)
		{
			$storage->set('restore.factory', $factory);
		}

		// Update progress indicators
		$bytesIn  = $storage->get('progress.bytesIn', 0) + ($data?->bytesIn ?? 0);
		$bytesOut = $storage->get('progress.bytesOut', 0) + ($data?->bytesOut ?? 0);
		$files    = $storage->get('progress.files', 0) + ($data?->files ?? 0);
		$percent  = $data?->percent ?? 0;

		$storage->set('progress.bytesIn', $bytesIn);
		$storage->set('progress.bytesOut', $bytesOut);
		$storage->set('progress.files', $files);
		$storage->set('progress.percent', $percent);

		if ($isDone)
		{
			$storage->set('progress.percent', 100);

			// Unlike restore.php, we don't need the serialised instance to run the post-extraction finalisation
			$storage->set('restore.factory', null);

			return true;
		}

		return false;
	}

	/**
	 * Steps through the update extraction on Joomla 4.0.4 or later
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  bool  True if we're done extracting, false otherwise
	 * @throws  GuzzleException
	 */
	private function j404ExtractStep(Site $site, Registry $storage): bool
	{
		$factory = $storage->get('restore.factory', null);

		if ($factory === null)
		{
			throw new LogicException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_FACTORY_GONE'));
		}

		$data = $this->doExtractAjax(
			$site, $storage, [
				'task'     => 'stepExtract',
				'instance' => $factory,
			]
		);

		return $this->handleJ404ExtractResponse($data, $storage);
	}

	/**
	 * Executes the post-extraction code in Joomla Update's restore.php or extract.php
	 *
	 * The restore.php file is used by Joomla 4.0.0–4.0.3 inclusive. It is, in fact, a very old version of Akeeba
	 * Restore we retired in 2016.
	 *
	 * The extract.php file is used by Joomla 4.0.4 and later. It is a rewritten and refactored version of the
	 * extraction script which I contributed to Joomla: https://github.com/joomla/joomla-cms/pull/35388
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function runPostExtract(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$url = $this->getExtractUrl($site);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_POSTEXTRACT',
				$site->id,
				$site->name
			)
		);

		if (str_ends_with($url, 'restore.php'))
		{
			$this->j40ExtractFinalise($site, $storage);
		}
		else
		{
			$this->j404ExtractFinalise($site, $storage);
		}

		$this->advanceState();
	}

	/**
	 * Finalise the update extraction on Joomla 4.0.0–4.0.3
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function j40ExtractFinalise(Site $site, Registry $storage): void
	{
		$factory = $storage->get('restore.factory', null);

		if ($factory === null)
		{
			throw new LogicException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_FACTORY_GONE'));
		}

		$data = [
			'task'    => 'finalizeRestore',
			'factory' => $factory,
		];

		$this->doEncryptedAjax($site, $storage, $data);

		$storage->set('restore.factory', null);
	}

	/**
	 * Finalise the update extraction on Joomla 4.0.4 or later
	 *
	 * @param   Site      $site     The site we are working on
	 * @param   Registry  $storage  The temporary storage for the update task
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function j404ExtractFinalise(Site $site, Registry $storage): void
	{
		$data = $this->doExtractAjax($site, $storage, ['task' => 'finalizeUpdate']);

		if (($data?->status ?? false) === false)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_EXTRACTION_FAILED',
					$data?->message ?? 'Unknown error'
				)
			);
		}
	}

	/**
	 * Executes the post-upgrade code in Joomla Update itself.
	 *
	 * This is different to runPostExtract. The code executed by runPostExtract is in restore.php/extract.php which runs
	 * outside Joomla itself. It is responsible for cleaning up the filesystem of leftover files to prevent the updated
	 * site having a mix of old and new code which would break the site.
	 *
	 * The code triggered in runFinalise is in the Joomla Update model code and executes inside Joomla. It is
	 * responsible for upgrading the database schema, add records for new core extensions, remove records for removed
	 * core extensions, and perform any database (data) migrations which need to take place.
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 * @throws  GuzzleException
	 */
	private function runFinalise(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_FINALISE',
				$site->id,
				$site->name
			)
		);

		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update/postupdate');
		$response = $httpClient->post($url, $options);

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_FINALISE_FAILED'));
		}

		$this->advanceState();
	}

	/**
	 * Forcibly reload the update information after updating a site.
	 *
	 * This is required when Joomla offers only "step" updates. For example, you can only update Joomla 4.0.0–4.0.3 to
	 * 4.0.4. Only **then** can you see further updates. By forcibly reloading the updates on the remote site we allow
	 * these "stepped" updates to become discovered faster than relying on the natural update information timeout in
	 * Joomla and Panopticon alone.
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 */
	private function runReloadUpdates(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_RELOAD_UPDATES',
				$site->id,
				$site->name
			)
		);

		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update?force=1');
		$response = $httpClient->get($url, $options);

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_RELOAD_UPDATES_FAILED')
			);
		}

		$this->advanceState();
	}

	/**
	 * Forcibly reload the site information after updating it
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 */
	private function runSiteInfo(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_RELOAD_SITEINFO',
				$site->id,
				$site->name
			)
		);

		$callback = $this->container->taskRegistry->get('refreshsiteinfo');

		$dummy         = new \stdClass();
		$dummyRegistry = new Registry();

		$dummyRegistry->set('limitStart', 0);
		$dummyRegistry->set('limit', 10);
		$dummyRegistry->set('force', true);
		$dummyRegistry->set('filter.ids', [$site->id]);

		$return = $callback($dummy, $dummyRegistry);

		$this->advanceState();
	}

	/**
	 * Executes the event which take place AFTER the update itself
	 *
	 * @param   object    $task     The current task object
	 * @param   Registry  $storage  The task's temporary storage
	 *
	 * @return  void
	 */
	private function runAfterEvents(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_POSTUPDATE_EVENTS',
				$site->id,
				$site->name
			)
		);

		if (!$this->runEvent('onAfterJoomlaUpdate', $task, $storage))
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_LOG_POSTUPDATE_WILL_CONTINUE',
					$site->id,
					$site->name
				)
			);
		}

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_POSTUPDATE_FINISHED',
				$site->id,
				$site->name
			)
		);

		$this->advanceState();
	}

	private function sendEmail(
		string $emailKey,
		Registry $storage,
		array $permissions = ['panopticon.super', 'panopticon.admin', 'panopticon.editown'],
		array $additionalVariables = []
	)
	{
		$this->logger->debug(
			sprintf(
				'Enqueueing email with template %s',
				$emailKey
			),
			$additionalVariables
		);

		$variables            = $storage->get('email_variables', []);
		$variables            = (array) $variables;
		$variables            = array_merge($variables, $additionalVariables);
		$perLanguageVariables = (array) $storage->get('email_variables_by_lang', []) ?: [];

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('email_variables_by_lang', $perLanguageVariables);
		$data->set('permissions', $permissions);
		$data->set('email_cc', $storage->get('email_cc', []));

		$this->enqueueEmail($data, $storage->get('site_id'), 'now');
	}

	/**
	 * @param   Site      $site
	 * @param   Registry  $storage
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	private function doSinglePartDownload(Site $site, Registry $storage): void
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_JOOMLAUPDATE_LOG_DOWNLOADING',
				$site->id,
				$site->name
			)
		);

		// Force reload the update information (in case the latest available Joomla version changed)
		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update?force=1');
		$response = $httpClient->get($url, $options);

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_RELOAD_UPDATES_FAILED')
			);
		}

		// Then, download the update
		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update/download');
		$response = $httpClient->post($url, $options);
		$json     = $this->sanitizeJson($response->getBody()->getContents());

		try
		{
			$raw = @json_decode($json);
		}
		catch (Exception $e)
		{
			$raw = null;
		}

		if (empty($raw) || empty($raw->data?->attributes?->basename))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_DOWNLOAD_FAILED'));
		}

		$baseName = $raw->data?->attributes?->basename;
		$check    = $raw->data?->attributes?->check ?? false;

		if (!$check)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_INVALID_CHECKSUM'));
		}

		$storage->set('update.basename', $baseName);
		$storage->set('update.check', $check);
	}

	/**
	 * Sends a success email
	 *
	 * @param   object    $task
	 * @param   Registry  $storage
	 *
	 * @return  void
	 * @since   1.0.4  Moved into its own method
	 */
	private function sendSuccessEmail(object $task, Registry $storage): void
	{
		// Ensure there are not stray transactions
		try
		{
			$this->container->db->transactionCommit();
		}
		catch (Exception)
		{
			// Okay...
		}

		// Get the start time, end time, and actual duration
		$startTime = $storage->get('start_timestamp', null);
		$endTime   = $startTime === null ? null : time();
		$duration  = $startTime === null ? null : $endTime - $startTime;

		/**
		 * Only send emails if we didn't reinstall the same version AND if we are asked to send emails after
		 * Joomla! update.
		 */
		$vars       = $storage->get('email_variables', []);
		$vars       = (array) $vars;
		$newVersion = $vars['NEW_VERSION'] ?? null;
		$oldVersion = $vars['OLD_VERSION'] ?? null;

		$perLanguageVariables = [];
		foreach ($this->getAllKnownLanguages() as $lang)
		{
			$langObject = $this->getContainer()->languageFactory($lang);

			if ($startTime === null)
			{
				$perLanguageVariables[$lang] = [
					'START_TIME' => $langObject->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_TIME'),
					'END_TIME'   => $langObject->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_TIME'),
					'DURATION'   => $langObject->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_DURATION'),
				];
			}
			else
			{
				$basicHtmlHelper = $this->container->html->basic;

				$perLanguageVariables[$lang] = [
					'START_TIME' => $basicHtmlHelper->date('@' . $startTime, $langObject->text('DATE_FORMAT_LC7')),
					'END_TIME'   => $basicHtmlHelper->date('@' . $endTime, $langObject->text('DATE_FORMAT_LC7')),
					'DURATION'   => $this->timeAgo($startTime, $endTime, languageObject: $langObject),
				];
			}
		}

		$storage->set('email_variables_by_lang', $perLanguageVariables);

		if (
			!empty($newVersion) && !empty($oldVersion) && $newVersion != $oldVersion
			&& $storage->get('email_after', true)
		)
		{
			$this->sendEmail('joomlaupdate_installed', $storage, ['panopticon.super', 'panopticon.manage']);
		}

		$this->advanceState();
	}
}
