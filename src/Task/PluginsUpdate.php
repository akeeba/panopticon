<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Html2Text;
use Akeeba\Panopticon\Helper\LanguageListTrait;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Akeeba\Panopticon\View\Mailtemplates\Html;
use Awf\Registry\Registry;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

#[AsTask(name: 'pluginsupdate', description: 'PANOPTICON_TASKTYPE_PLUGINSUPDATE')]
class PluginsUpdate extends AbstractCallback
{
	use ApiRequestTrait;
	use SiteNotificationEmailTrait;
	use EmailSendingTrait;
	use LanguageListTrait;
	use JsonSanitizerTrait;
	use SaveSiteTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->findOrFail($task->site_id);

		if ($site->cmsType() !== CMSType::WORDPRESS)
		{
			throw new RuntimeException('This is not a WordPress site!');
		}

		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Get the queue
		$queueKey = sprintf(QueueTypeEnum::PLUGINS->value, $task->site_id);
		$queue    = $this->container->queueFactory->makeQueue($queueKey);
		$item     = $queue->pop();

		// It is possible for this to happen if another process snatched the last item of the queue before we did.
		if ($item === null)
		{
			$this->logger->info(
				sprintf(
					'Plugin and themes updates for site #%d (%s): Queue empty, done installing updates',
					$site->id, $site->name
				)
			);

			// Email say we are done
			$this->enqueueResultsEmail($site, $storage);

			// Reload the update information from the site (you never know…)
			$this->reloadExtensionInformation($site);

			/**
			 * DO NOT REMOVE THIS CHECK - THIS IS A CONCURRENCY SANITY CHECK
			 *
			 * It is possible that there were no items in the queue when we tried to pop an item. However, between then
			 * and now we've done a fair amount of work which gives enough time for another process to enqueue a new
			 * item. In this case we don't want to return OK; we want to return WILL_RESUME instead.
			 */
			if ($queue->count() === 0)
			{
				return Status::OK->value;
			}

			$this->logger->info(
				sprintf(
					'Plugin and themes updates for site #%d (%s): Queue item added before marking ourselves done; will resume installing updates later.',
					$site->id, $site->name
				)
			);

			return Status::WILL_RESUME->value;
		}

		// Tell the site to install the update.
		$this->installUpdate($site, $item, $storage);

		return Status::WILL_RESUME->value;
	}

	private function installUpdate(Site $site, QueueItem $item, Registry $storage): void
	{
		$updateStatus = (array) $storage->get('updateStatus', []);

		// This is the plugin/theme ID we are asked to install
		$data = $item->getData();

		if (is_object($item->getData()))
		{
			// This handles legacy data which might be in the database. Eventually, it can be removed.
			$softwareId     = (string) ($data->id ?? 0);
			$updateMode     = $data->mode ?? 'update';
			$initiatingUser = $data->initiatingUser ?? 0;
		}
		else
		{
			$softwareId     = (string) $item->getData();
			$updateMode     = 'update';
			$initiatingUser = 0;
		}

		if (empty($softwareId))
		{
			$this->logger->warning(
				sprintf(
					'Plugin and theme updates for site #%d (%s): invalid software ID “%s” will be ignored',
					$site->id, $site->name, $softwareId
				)
			);

			return;
		}

		// Try to get the extension information from the site's config
		$siteConfig = $site->getConfig() ?? new Registry();
		$extensions = (array) $siteConfig->get('extensions.list');

		$extKeys = array_map(
			fn($item) => (($item->type === 'plugin') ? 'plg_' : 'tpl_') .
		             trim(implode('_', [$item->folder, $item->element]), '_'),
			$extensions
		);

		$extensions = array_combine($extKeys, $extensions);

		if (!isset($extensions[$softwareId]))
		{
			$this->logger->warning(
				sprintf(
					'Plugin and themes updates for site #%d (%s): software ID “%s” does not exist and will be ignored',
					$site->id, $site->name, $softwareId
				)
			);

			return;
		}

		// Record the "last seen" new version in the site's configuration.
		$this->recordLastSeenVersion($site, $softwareId);

		if ($updateMode === 'email')
		{
			$this->logger->info(
				sprintf(
					'Plugin and themes updates for site #%d (%s): will notify by email for %s “%s” (ID: %s). The update will NOT be installed automatically.',
					$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name,
					$softwareId
				)
			);

			// Enqueue update email
			$this->enqueueUpdateEmail($site, $extensions[$softwareId]);

			return;
		}

		// Log that we are about to install an update
		$this->logger->info(
			sprintf(
				'Plugin and themes updates for site #%d (%s): attempting to install update for %s “%s” (ID: %s)',
				$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name, $softwareId
			)
		);

		// Cache the versions; we use them in reports
		$oldVersion = $extensions[$softwareId]->version?->current;
		$newVersion = $extensions[$softwareId]->version?->new;

		// Send the HTTP request
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($site, '/v1/panopticon/update');

		$urlSlugs = array_filter([
			($extensions[$softwareId]->type === 'plugin') ? 'plugin' : 'theme',
			$extensions[$softwareId]->folder,
			$extensions[$softwareId]->element,
		]);

		$url = rtrim($url, '/') . '/' . implode('/', $urlSlugs);

		try
		{
			$response = $httpClient->post($url, $options);
		}
		catch (GuzzleException $e)
		{
			$this->logger->error(
				sprintf(
					'Plugin and themes updates for site #%d (%s): failed installing update for %s “%s”. Guzzle error: %s',
					$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name,
					$e->getMessage()
				)
			);

			$updateStatus[$softwareId] = [
				'type'     => $extensions[$softwareId]->type,
				'name'     => $extensions[$softwareId]->name,
				'status'   => 'exception',
				'messages' => [$e->getMessage()],
			];
			$storage->set('updateStatus', $updateStatus);

			// Log failed update report
			$this->logReport(
				$site,
				$extensions[$softwareId],
				$oldVersion,
				$newVersion,
				false,
				$e,
				$initiatingUser
			);

			return;
		}

		$rawJSONData = $this->sanitizeJson($response->getBody()->getContents());

		try
		{
			$decodedResponse = @json_decode($rawJSONData ?? '{}');

			if (empty($decodedResponse))
			{
				throw new \RuntimeException('No JSON object returned from the WordPress API application.');
			}
		}
		catch (Exception $e)
		{
			$this->logger->error(
				sprintf(
					'Plugin and themes updates for site #%d (%s): failed installing update for %s “%s”. Invalid JSON reply: %s',
					$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name,
					$response->getBody()
				)
			);

			$updateStatus[$softwareId] = [
				'type'     => $extensions[$softwareId]->type,
				'name'     => $extensions[$softwareId]->name,
				'status'   => 'invalid_json',
				'messages' => [$e->getMessage()],
			];
			$storage->set('updateStatus', $updateStatus);

			// Log failed update report
			$this->logReport(
				$site,
				$extensions[$softwareId],
				$oldVersion,
				$newVersion,
				false,
				$e,
				$initiatingUser
			);

			return;
		}

		// Try to get the returned data.
		$errorCode            = $decodedResponse?->code ?? null;
		$errorMessage         = $decodedResponse?->message ?? null;
		$errorData            = $decodedResponse?->data ?? [];
		$status               = (bool) ($decodedResponse?->status ?? false);
		$installationMessages = $decodedResponse?->messages ?? '';

		$isError = !empty($errorCode) || !$status;

		// This should never happen, really.
		if (is_null($errorCode) && !$status)
		{
			$this->logger->error(
				sprintf(
					'Plugin and themes updates for site #%d (%s): failed installing update for %s “%s”. WordPress returned invalid data',
					$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name
				),
				[$rawJSONData]
			);

			$updateStatus[$softwareId] = [
				'type'     => $extensions[$softwareId]->type,
				'name'     => $extensions[$softwareId]->name,
				'status'   => 'error',
				'messages' => [$rawJSONData ?? ''],
			];
			$storage->set('updateStatus', $updateStatus);

			// Log failed update report
			$this->logReport(
				$site,
				$extensions[$softwareId],
				$oldVersion,
				$newVersion,
				false,
				[
					'invalidJson' => $rawJSONData,
				],
				$initiatingUser
			);

			return;
		}

		if ($isError)
		{
			$this->logger->error(
				sprintf(
					'Plugin and themes updates for site #%d (%s): failed installing update for %s “%s”. WordPress reported error “%s”: %s',
					$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name,
					$errorCode, $errorMessage
				),
				(array) $decodedResponse
			);

			$updateStatus[$softwareId] = [
				'type'     => $extensions[$softwareId]->type,
				'name'     => $extensions[$softwareId]->name,
				'status'   => 'error',
				'messages' => [$errorMessage],
			];
			$storage->set('updateStatus', $updateStatus);

			// Log failed update report
			$this->logReport(
				$site,
				$extensions[$softwareId],
				$oldVersion,
				$newVersion,
				false,
				[
					'messages' => [$errorMessage],
				],
				$initiatingUser
			);

			return;
		}

		$this->logger->debug(
			sprintf(
				'Plugin and themes updates for site #%d (%s): installed update for %s “%s”',
				$site->id, $site->name, $extensions[$softwareId]->type, $extensions[$softwareId]->name
			),
			(array) $decodedResponse
		);

		// Update extensions.list and extensions.hasUpdates in the site's config storage
		$this->saveSite(
			$site,
			function (Site $site) use ($softwareId) {
				// Reload the site information, in case it changed while we were installing updates
				$site->findOrFail($site->id);

				// Get the extensions list
				$siteConfig = $site->getConfig() ?? new Registry();;
				$extensions = (array) $siteConfig->get('extensions.list');

				// Make sure our updated extension didn't get uninstalled in the meantime
				if (!isset($extensions[$softwareId]))
				{
					throw new \RuntimeException('The software (plugin or theme) went away.');
				}

				// Mark the extension as not having updates
				$extensions[$softwareId]->version->new = null;
				$siteConfig->set('extensions.list', $extensions);

				// Set a flag for the existence of updates
				$hasUpdates = array_reduce(
					$extensions,
					function (bool $carry, object $item): int {
						$current = $item?->version?->current;
						$new     = $item?->version?->new;

						if ($carry || empty($current) || empty($new))
						{
							return $carry;
						}

						return version_compare($current, $new, 'lt');
					},
					false
				);

				$siteConfig->set('extensions.hasUpdates', $hasUpdates);

				// Update the site's JSON config field
				$site->config = $siteConfig->toString('JSON');
			}
		);

		$updateStatus[$softwareId] = [
			'type'     => $extensions[$softwareId]->type,
			'name'     => $extensions[$softwareId]->name,
			'status'   => 'success',
			'messages' => [$installationMessages],
		];
		$storage->set('updateStatus', $updateStatus);

		// Log successful update report
		$this->logReport(
			$site,
			$extensions[$softwareId],
			$oldVersion,
			$newVersion,
			true,
			[
				'messages' => [$installationMessages],
			],
			$initiatingUser
		);
	}

	/**
	 * Log an extension update installation report entry
	 *
	 * @param   Site      $site            The site we are installing extension updates on
	 * @param   object    $extension       The extension object being updated
	 * @param   bool      $status          Did we succeed?
	 * @param   mixed     $e               Additional context (on failure)
	 * @param   int|null  $initiatingUser  The initiating user of this update installation
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	private function logReport(
		Site $site, object $extension, string $oldVersion, string $newVersion, bool $status = true, mixed $e = null,
		?int $initiatingUser = null
	): void
	{
		$report = Reports::fromExtensionUpdateInstalled(
			$site->id,
			$this->container->mvcFactory->makeTempModel('Sysconfig')
				->getExtensionShortname(
					$extension->type, $extension->element, $extension->folder, $extension->client_id
				),
			$extension->name,
			$oldVersion,
			$newVersion,
			$status,
			$e
		);

		if ($initiatingUser !== null && $initiatingUser !== 0)
		{
			$report->created_by = $initiatingUser;
		}

		try
		{
			$report->save();
		}
		catch (\Throwable $e)
		{
			// Whatever...
		}
	}

	/**
	 * Enqueue an email with the resilts of the update
	 *
	 * @param   Site      $site
	 * @param   Registry  $storage
	 *
	 * @return  void
	 * @throws  \Awf\Exception\App
	 * @since   1.0.0
	 */
	private function enqueueResultsEmail(Site $site, Registry $storage): void
	{
		// Render the messages as HTML
		$updateStatus = (array) $storage->get('updateStatus', []);

		if (empty($updateStatus))
		{
			// We did not do anything. No need to email the user to tell them we did sod all.
			return;
		}

		$perLanguageVars = [];

		foreach ($this->getAllKnownLanguages() as $language)
		{
			[$rendered, $renderedText] = $this->getRenderedResultsForEmail($language, $updateStatus, $site);

			$perLanguageVars[$language] = [
				'RENDERED_HTML' => $rendered,
				'RENDERED_TEXT' => $renderedText,
			];
		}

		$emailKey  = 'plugins_update_done';
		$variables = [
			'SITE_NAME' => $site->name,
			'SITE_URL'  => $site->getBaseUrl(),
		];

		// Get the CC email addresses
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

		$cc = $this->getSiteNotificationEmails($config);

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('email_variables_by_lang', $perLanguageVars);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $cc);

		$this->enqueueEmail($data, $site->id, 'now');
	}

	private function enqueueUpdateEmail(Site $site, ?object $extension): void
	{
		$emailKey  = 'plugin_update_found';
		$variables = [
			'SITE_NAME'            => $site->name,
			'SITE_URL'             => $site->getBaseUrl(),
			'OLD_VERSION'          => $extension?->version?->current,
			'NEW_VERSION'          => $extension?->version?->new,
			'SOFTWARE_TYPE'        => $extension?->type,
			'SOFTWARE_NAME'        => $extension?->name,
			'SOFTWARE_DESCRIPTION' => $extension?->description,
			'SOFTWARE_AUTHOR'      => $extension?->author,
			'EXTENSION_TYPE'        => $extension?->type,
			'EXTENSION_NAME'        => $extension?->name,
			'EXTENSION_DESCRIPTION' => $extension?->description,
			'EXTENSION_AUTHOR'      => $extension?->author,
		];

		// Get the CC email addresses
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

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $this->getSiteNotificationEmails($config));

		$this->enqueueEmail($data, $site->id, 'now');
	}

	private function reloadExtensionInformation(Site $site): void
	{
		$this->logger->info(
			sprintf(
				'Refreshing the extension update information for site #%d (%s)',
				$site->id,
				$site->name
			)
		);

		$callback = $this->container->taskRegistry->get('refreshinstalledextensions');

		$dummy         = new \stdClass();
		$dummyRegistry = new Registry();

		$dummyRegistry->set('limitStart', 0);
		$dummyRegistry->set('limit', 10);
		$dummyRegistry->set('force', true);
		$dummyRegistry->set('filter.ids', [$site->id]);

		$return = $callback($dummy, $dummyRegistry);
	}

	/**
	 * Records the last seen newest version of an extension in a site's configuration.
	 *
	 * @param   Site  $site        The site which the extension belongs to.
	 * @param   int   $softwareId  The extension to record information for.
	 *
	 * @return  void
	 */
	private function recordLastSeenVersion(Site $site, string $softwareId): void
	{
		$this->saveSite(
			$site,
			function (Site $site) use ($softwareId) {
				$siteConfig                    = $site->getConfig() ?? new Registry();
				$lastSeenVersions              = $siteConfig->get('director.pluginupdates.lastSeen', []) ?: [];
				$lastSeenVersions              = is_object($lastSeenVersions) ? (array) $lastSeenVersions
					: $lastSeenVersions;
				$lastSeenVersions              = is_array($lastSeenVersions) ? $lastSeenVersions : [];
				$extensions                    = (array) $siteConfig->get('extensions.list');
				$extensionItem                 = $extensions[$softwareId] ?? null;
				$latestVersion                 = $extensionItem?->version?->new;
				$lastSeenVersions[$softwareId] = $latestVersion;

				$siteConfig->set('director.pluginupdates.lastSeen', $lastSeenVersions);
				$site->setFieldValue('config', $siteConfig->toString());
			}
		);
	}

	/**
	 * @param   string  $language
	 * @param   array   $updateStatus
	 * @param   Site    $site
	 *
	 * @return array
	 * @throws \Awf\Exception\App
	 */
	private function getRenderedResultsForEmail(string $language, array $updateStatus, Site $site): array
	{
		$possibleTemplates       = [
			'Mailtemplates/mail_plugins_update_done.' . $language,
			'Mailtemplates/mail_plugins_update_done',
		];
		$container               = clone $this->container;
		$container['mvc_config'] = [
			'template_path' => [
				APATH_ROOT . '/ViewTemplates/Mailtemplates',
				APATH_USER_CODE . '/ViewTemplates/Mailtemplates',
			],
		];
		$container->language->loadLanguage($language ?: $container->appConfig->get('language', 'en-GB'));
		$fakeView = new Html($container);
		$rendered = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$rendered = $rendered ?: $fakeView->loadAnyTemplate(
					$template,
					[
						'updateStatus' => $updateStatus,
						'site'         => $site,
					]
				);
			}
			catch (Exception $e)
			{
				// Expected, as the language override may not be in place.
			}
		}

		// Render the messages as plain text
		$possibleTemplates = [
			'Mailtemplates/mail_plugins_update_done.' . $language . 'text',
			'Mailtemplates/mail_plugins_update_done.text',
		];
		$renderedText      = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$renderedText = $renderedText ?: $fakeView->loadAnyTemplate(
					$template,
					[
						'updateStatus' => $updateStatus,
						'site'         => $site,
					]
				);
			}
			catch (Exception $e)
			{
				// Expected, as the language override may not be in place.
			}
		}

		// Fall back to automatic HTML to plain text conversion
		if (empty($renderedText))
		{
			$renderedText = (new Html2Text($rendered))->getText();
		}

		return [$rendered, $renderedText];
	}
}