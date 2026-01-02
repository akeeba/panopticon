<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Exception\CoreUpdate\NonEmailedRuntimeException;
use Akeeba\Panopticon\Helper\LanguageListTrait;
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
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

#[AsTask(
	name: 'wordpressupdate',
	description: 'PANOPTICON_TASKTYPE_WORDPRESSUPDATE'
)]
class WordPressUpdate extends AbstractCallback
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
			if ($site->cmsType() !== CMSType::WORDPRESS)
			{
				throw new RuntimeException('This is not a WordPress site!');
			}

			switch ($this->currentState)
			{
				case 'init':
				default:
					$this->runInit($task, $storage);
					break;

				case 'beforeEvents':
					$this->runBeforeEvents($task, $storage);
					break;

				case 'backup':
					$this->runBackup($task, $storage);
					break;

				case 'update':
					$this->runUpdate($task, $storage);
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
			if ($storage->get('email_error', true) && !$e instanceof NonEmailedRuntimeException)
			{
				$this->sendEmail(
					'wordpressupdate_failed', $storage, ['panopticon.super', 'panopticon.manage'], [
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
		catch (Throwable)
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
	 * - init: Initial state, performs sanity self-checks.
	 * - beforeEvents: Executes any events which need to precede the update.
	 * - backup: Take a backup.
	 * - update: Tells WordPress to update itself (sadly, it's an atomic operation).
	 * - siteInfo: Fetch the site information afresh.
	 * - afterEvents: Executes any events which need to run after the update.
	 * - email: Send the success email.
	 * - finish: Signals the need to return successfully.
	 *
	 * @return  void
	 */
	protected function advanceState(): void
	{
		$this->currentState = match ($this->currentState)
		{
			default => 'beforeEvents',
			'beforeEvents' => 'backup',
			'backup' => 'update',
			'update' => 'siteInfo',
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
		$emailVariables = [
			'NEW_VERSION' => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_VERSION'),
			'OLD_VERSION' => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_VERSION'),
			'SITE_NAME'   => $this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_LBL_UNKNOWN_SITE'),
			'SITE_URL'    => 'https://www.example.com',
		];
		$storage->set('email_variables', $emailVariables);

		// Remember when we started installing the update
		$storage->set('start_timestamp', time());

		// Try to get the site
		$site = $this->getSite($task);

		$emailVariables = array_merge(
			$emailVariables,
			[
				'SITE_NAME'   => $site->name,
				'SITE_URL'    => $site->getBaseUrl(),
			]
		);
		$storage->set('email_variables', $emailVariables);
		$storage->set('site_id', $site->id);

		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Is the site enabled?
		if (!$site->enabled)
		{
			throw new NonEmailedRuntimeException(
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
			$config = @json_decode((string) $config);
		}
		catch (Exception)
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

		$emailVariables = array_merge(
			$emailVariables,
			[
				'NEW_VERSION' => $latestVersion,
				'OLD_VERSION' => $currentVersion,
			]
		);
		$storage->set('email_variables', $emailVariables);
		$storage->set('email_cc', $this->getSiteNotificationEmails($config));
		$storage->set('email_after', (bool) ($config?->config?->core_update?->email_after ?? true));
		$storage->set('email_error', (bool) ($config?->config?->core_update?->email_error ?? true));


		if (
			!$force && !empty($currentVersion) && !empty($latestVersion)
			&& version_compare($currentVersion, $latestVersion, 'ge')
		)
		{
			throw new NonEmailedRuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_NO_UPDATE_AVAILABLE', $site->id,
					$site->name, $currentVersion, $latestVersion
				)
			);
		}

		if (!empty($currentVersion) && !empty($latestVersion))
		{
			$this->logger->info(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_LOG_WILL_BE_UPDATED',
					$site->id,
					$site->name,
					$currentVersion,
					$latestVersion
				)
			);
		}
		else
		{
			throw new NonEmailedRuntimeException(
				sprintf(
					'The current or latest version is missing on site #%d (%s). Is the site already updated?',
					$site->id,
					$site->name,
				)
			);
		}

		// Finally, advance the state
		$this->advanceState();
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

		if (!$this->runEvent('onBeforeWordPressUpdate', $task, $storage))
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

		if (!$this->runEvent('onAfterWordPressUpdate', $task, $storage))
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
			$this->sendEmail('wordpressupdate_installed', $storage, ['panopticon.super', 'panopticon.manage']);
		}

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

	private function runUpdate(object $task, Registry $storage): void
	{
		$site = $this->getSite($task);

		/** @var Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$this->logger->info(
			$this->getLanguage()->sprintf(
				'PANOPTICON_TASK_WORDPRESSUPDATE_LOG_INSTALLING',
				$site->id,
				$site->name
			)
		);

		[$url, $options] = $this->getRequestOptions($site, '/v1/panopticon/core/update');
		$response = $httpClient->post($url, $options);

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_HTTP',
					$site->id,
					$site->name,
					$response->getStatusCode()
				)
			);
		}

		$body = $response->getBody();
		$body = $this->sanitizeJson(trim($body ?? ''));

		$this->logger->debug('Received response', ['response' => $body]);

		if (!$this->jsonValidate($body))
		{
			throw new RuntimeException(
				throw new RuntimeException(
					$this->getLanguage()->sprintf(
						'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_INVALID_JSON',
						$site->id,
						$site->name
					)
				)
			);
		}

		$parsedBody = json_decode($body, true);
		$parsedData = $parsedBody['data'] ?? [];

		if (($parsedBody['code'] ?? 200) != 200)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_WORDPRESS',
					$site->id,
					$site->name,
					$parsedBody['code'] ?? 200,
					$parsedBody['message'] ?? '(no details)',
				)
			);
		}

		if (!($parsedData['status'] ?? $parsedBody['status'] ?? false))
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_STATUS_FALSE',
					$site->id,
					$site->name
				)
			);
		}

		if (!($parsedData['found'] ?? $parsedBody['found'] ?? false))
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_WORDPRESSUPDATE_ERR_NOT_FOUND',
					$site->id,
					$site->name
				)
			);
		}

		$this->advanceState();
	}
}