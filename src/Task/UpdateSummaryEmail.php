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
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Akeeba\Panopticon\View\Mailtemplates\Html;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Exception;
use RuntimeException;

#[AsTask(
	name: 'updatesummaryemail',
	description: 'PANOPTICON_TASKTYPE_UPDATESUMMARYEMAIL'
)]
class UpdateSummaryEmail extends AbstractCallback
{
	use EmailSendingTrait;
	use LanguageListTrait;
	use SiteNotificationEmailTrait;

	/**
	 * Should I report code updates in the email?
	 *
	 * @var    bool
	 * @since  1.0.5
	 */
	private bool $reportCoreUpdates = true;

	/**
	 * Should I report extension updates in the email?
	 *
	 * @var    bool
	 * @since  1.0.5
	 */
	private bool $reportExtensionUpdates = true;

	/**
	 * Should I prevent duplicate emails, listing the same updates?
	 *
	 * @var    bool
	 * @since  1.0.5
	 */
	private bool $preventDuplicates = true;

	/**
	 * The last identifier for the updates summary, used with $preventDuplicates.
	 *
	 * @var    string|null
	 * @since  1.0.5
	 */
	private ?string $lastIdentifier = null;

	private ?object $coreUpdate = null;

	private ?array $extensionUpdates = null;

	/**
	 * The site we are reporting updates for.
	 *
	 * @var    Site|null
	 * @since  1.0.5
	 */
	private ?Site $site = null;

	/**
	 * The user group to send emails to.
	 *
	 * When this is set, emails will be sent to all users of the selected group regardless of whether they can see or
	 * manage the site the report is generated for. If this is not set (empty array) emails will be sent to any user
	 * who has the Super global privilege and/or is allowed to manage the site.
	 *
	 * @var array|int[]
	 */
	private array $emailGroups;

	public function __invoke(object $task, Registry $storage): int
	{
		$this->initialiseObject($task);

		if (!$this->reportCoreUpdates && !$this->reportExtensionUpdates)
		{
			$this->logger->warning(
				sprintf(
					'The Scheduled Updates Summary Email task #%u for site #%u (%s) is set up to notify about no updates. There is nothing to do here. Did you mean to disable the task instead?',
					$task->id ?? 0,
					$this->site->id,
					$this->site->name
				)
			);

			return Status::OK->value;
		}

		// Collect the update information
		$this->findCoreUpdates();
		$this->findExtensionUpdates();

		// If we have no updates to report, exit cleanly.
		if (empty($this->coreUpdate) && empty($this->extensionUpdates))
		{
			$this->logger->info(
				sprintf(
					'Scheduled Updates Summary Email task #%u for site #%u (%s): no updates to report.',
					$task->id ?? 0,
					$this->site->id,
					$this->site->name
				)
			);

			return Status::OK->value;
		}

		// Get the new updates identifier, and compare it with the last one.
		if ($this->preventDuplicates)
		{
			$currentIdentifier = $this->getUpdatesIdentifier();

			if ($currentIdentifier === $this->lastIdentifier)
			{
				$this->logger->info(
					sprintf(
						'Scheduled Updates Summary Email task #%u for site #%u (%s): an email for the same updates has already been sent and I was asked to not send duplicate emails.',
						$task->id ?? 0,
						$this->site->id,
						$this->site->name
					)
				);

				return Status::OK->value;
			}

			$this->updateTaskWithIdentifier($task, $currentIdentifier);
		}

		$this->logger->info(
			sprintf(
				'Scheduled Updates Summary Email task #%u for site #%u (%s): enqueueing email message.',
				$task->id ?? 0,
				$this->site->id,
				$this->site->name
			)
		);

		$this->sendEmail();

		return Status::OK->value;
	}

	/**
	 * Initialise the object from the task parameters
	 *
	 * @param   object  $task
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	private function initialiseObject(object $task): void
	{
		$params = ($task->params instanceof Registry) ? $task->params : new Registry($task->params ?? null);

		$mailGroups                   = $params->get('email_groups', null) ?? null;
		$mailGroups                   = is_array($mailGroups) ? array_filter(ArrayHelper::toInteger($mailGroups)) : [];
		$this->reportCoreUpdates      = $params->get('core_updates', true);
		$this->reportExtensionUpdates = $params->get('extension_updates', true);
		$this->preventDuplicates      = $params->get('prevent_duplicates', true);
		$this->lastIdentifier         = $params->get('updates_identifier', null);
		$this->site                   = $this->getSite($task);
		$this->emailGroups            = $mailGroups;
	}

	/**
	 * Get the site object corresponding to the task we are currently handling.
	 *
	 * @param   object  $task
	 *
	 * @return  Site
	 * @since   1.0.5
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
	 * Returns the identifier (SHA-1) of the updates to report
	 *
	 * @return  string
	 * @since   1.0.5
	 */
	private function getUpdatesIdentifier(): string
	{
		return hash(
			'sha1',
			json_encode($this->coreUpdate) . '::#::' . json_encode($this->extensionUpdates)
		);
	}

	/**
	 * Find core updates for this site.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	private function findCoreUpdates(): void
	{
		$this->coreUpdate = null;
		$siteConfig       = $this->site->getConfig();

		if (!$this->reportCoreUpdates || !$siteConfig->get('core.canUpgrade', false))
		{
			return;
		}

		$this->coreUpdate = (object) [
			'current' => $siteConfig->get('core.current.version'),
			'latest'  => $siteConfig->get('core.latest.version'),
		];
	}

	/**
	 * Find extension updates for this site.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	private function findExtensionUpdates(): void
	{
		$this->extensionUpdates = null;

		if (!$this->reportExtensionUpdates)
		{
			return;
		}

		$siteConfig = $this->site->getConfig();

		$this->extensionUpdates = array_map(
			fn($item): array => [
				'id'         => $item->extension_id ?? '',
				'type'       => $item->type,
				'name'       => $item->name,
				'author'     => $item->author,
				'author_url' => $item->authorUrl,
				'current'    => $item->version?->current,
				'new'        => $item->version?->new,
			],
			array_filter(
				(array) ($siteConfig->get('extensions.list', []) ?: []),
				function ($item): bool {
					/**
					 * The extension needs to have update sites.
					 *
					 * Important! We do not check whether there is a valid Download Key at this point. This email will
					 * tell you which extensions have updates, even if their Download Key is missing / invalid. It will
					 * then be up to you to figure out what you should do next.
					 */
					if (!$item->hasUpdateSites)
					{
						return false;
					}

					/**
					 * Can't update an extension without version information, or when the installed version is newer
					 * than the ostensibly latest version.
					 */
					$currentVersion = $item->version?->current;
					$newVersion     = $item->version?->new;

					if (empty($currentVersion) || empty($newVersion) || $currentVersion === $newVersion
					    || version_compare($currentVersion, $newVersion, 'ge'))
					{
						return false;
					}

					return true;
				}
			)
		) ?: null;
	}

	/**
	 * Update the current task object with the new updates identifier
	 *
	 * @param   object  $task
	 * @param   string  $currentIdentifier
	 *
	 * @return  void
	 * @throws  \Awf\Exception\App
	 * @throws  \Awf\Mvc\DataModel\Relation\Exception\ForeignModelNotFound
	 * @throws  \Awf\Mvc\DataModel\Relation\Exception\RelationTypeNotFound
	 * @since   1.0.5
	 */
	private function updateTaskWithIdentifier(object $task, string $currentIdentifier): void
	{
		// Make sure I have a Task object
		if (!$task instanceof Task)
		{
			return;
		}

		// Update the updates identifier with the task.
		$params = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$params->set('updates_identifier', $currentIdentifier);
		$task->save(
			[
				'params' => $params->toString('JSON'),
			]
		);
	}

	/**
	 * Get the rendered HTML and plaintext results which will be sent by email
	 *
	 * @param   string  $language
	 *
	 * @return  array
	 * @throws  \Awf\Exception\App
	 * @since   1.0.5
	 */
	private function getRenderedResultsForEmail(string $language): array
	{
		$possibleTemplates       = [
			'Mailtemplates/mail_scheduled_update_summary.' . $language,
			'Mailtemplates/mail_scheduled_update_summary',
		];
		$container               = clone $this->container;
		$container['mvc_config'] = [
			'template_path' => [
				APATH_ROOT . '/ViewTemplates/Mailtemplates',
				APATH_USER_CODE . '/ViewTemplates/Mailtemplates',
			],
		];
		$container->language->loadLanguage($language ?: $container->appConfig->get('language', 'en-GB'));
		$fakeView                = new Html($container);
		$rendered                = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$rendered = $fakeView->loadAnyTemplate(
					$template,
					[
						'reportCoreUpdates'      => $this->reportCoreUpdates,
						'coreUpdates'            => $this->coreUpdate,
						'reportExtensionUpdates' => $this->reportExtensionUpdates,
						'extensionUpdates'       => $this->extensionUpdates,
						'site'                   => $this->site,
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
			'Mailtemplates/mail_scheduled_update_summary.' . $language . 'text',
			'Mailtemplates/mail_scheduled_update_summary.text',
		];
		$renderedText      = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$renderedText = $fakeView->loadAnyTemplate(
					$template,
					[
						'reportCoreUpdates'      => $this->reportCoreUpdates,
						'coreUpdates'            => $this->coreUpdate,
						'reportExtensionUpdates' => $this->reportExtensionUpdates,
						'extensionUpdates'       => $this->extensionUpdates,
						'site'                   => $this->site,
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

	/**
	 * @return void
	 * @throws \Awf\Exception\App
	 */
	private function sendEmail(): void
	{
		// Get the language-specific rendering of the email body
		$perLanguageVars = [];

		foreach ($this->getAllKnownLanguages() as $language)
		{
			[$rendered, $renderedText] = $this->getRenderedResultsForEmail($language);

			$perLanguageVars[$language] = [
				'RENDERED_HTML' => $rendered,
				'RENDERED_TEXT' => $renderedText,
			];
		}

		// Set up the email
		$data = new Registry();
		$data->set('template', 'scheduled_update_summary');
		$data->set(
			'email_variables', [
				'SITE_NAME' => $this->site->name,
				'SITE_URL'  => $this->site->getBaseUrl(),
			]
		);
		$data->set('email_variables_by_lang', $perLanguageVars);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $this->getSiteNotificationEmails($this->site->getConfig()));

		if (!empty($this->emailGroups))
		{
			// Email groups selected. Send the emil ONLY to users belonging in these groups.
			$data->set('email_cc', []);
			$data->set('permissions', []);
			$data->set('email_groups', $this->emailGroups);
			$data->set('only_email_groups', true);
		}

		// Enqueue the email
		$this->enqueueEmail($data, $this->site->id, 'now');
	}

}