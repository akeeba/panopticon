<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Html2Text;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Mailtemplates\Html;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Awf\Text\Text;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerAwareTrait;

#[AsTask(name: 'extensionsupdate', description: 'PANOPTICON_TASKTYPE_EXTENSIONSUPDATE')]
class ExtensionsUpdate extends AbstractCallback
{
	use ApiRequestTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @var Site $site */
		$site = Model::getTmpInstance(null, 'Site', $this->container);
		$site->findOrFail($task->site_id);

		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $site->id));

		// Get the queue
		$queueKey = sprintf('extensions.%d', $task->site_id);
		$queue    = $this->container->queueFactory->makeQueue($queueKey);
		$item     = $queue->pop();

		// It is possible for this to happen if another process snatched the last item of the queue before we did.
		if ($item === null)
		{
			$this->logger->info(
				sprintf(
					'Extension updates for site #%d (%s): Queue empty, done installing updates',
					$site->id, $site->name
				)
			);

			// Email say we are done
			$this->enqueueEmail($site, $storage);

			// Reload the update information from the site (you never know…)
			$this->reloadExtensionInformation($site);

			/**
			 * DO NOT REMOVE THIS CHECK — THIS IS A CONCURRENCY SANITY CHECK
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
					'Extension updates for site #%d (%s): Queue item added before marking ourselves done; will resume installing updates later.',
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

		// This is the extension ID we are asked to install
		$extensionId = (int)$item->getData();

		if (empty($extensionId) || $extensionId <= 0)
		{
			$this->logger->warning(
				sprintf(
					'Extension updates for site #%d (%s): invalid extension ID “%d” will be ignored',
					$site->id, $site->name, $extensionId
				)
			);

			return;
		}

		// Try to get the extension information from the site's config
		$siteConfig = ($site->getFieldValue('config') instanceof Registry)
			? $site->getFieldValue('config')
			: (new Registry($site->getFieldValue('config')));
		$extensions = (array)$siteConfig->get('extensions.list');

		if (!isset($extensions[$extensionId]))
		{
			$this->logger->warning(
				sprintf(
					'Extension updates for site #%d (%s): extension ID “%d” does not exist and will be ignored',
					$site->id, $site->name, $extensionId
				)
			);

			return;
		}

		// Log that we are about to install an update
		$this->logger->info(
			sprintf(
				'Extension updates for site #%d (%s): attempting to install update for %s “%s” (EID: %d)',
				$site->id, $site->name, $extensions[$extensionId]->type, $extensions[$extensionId]->name, $extensionId
			)
		);

		// Send the HTTP request
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/update');

		$options = array_merge($options, [
			RequestOptions::FORM_PARAMS => [
				'eid' => [$extensionId],
			],
		]);
		try
		{
			$response = $httpClient->post($url, $options);
		}
		catch (GuzzleException $e)
		{
			$this->logger->error(
				sprintf(
					'Extension updates for site #%d (%s): failed installing update for %s “%s”. Guzzle error: %s',
					$site->id, $site->name, $extensions[$extensionId]->type, $extensions[$extensionId]->name,
					$e->getMessage()
				)
			);

			$updateStatus[$extensionId] = [
				'type'     => $extensions[$extensionId]->type,
				'name'     => $extensions[$extensionId]->name,
				'status'   => 'exception',
				'messages' => [$e->getMessage()],
			];
			$storage->set('updateStatus', $updateStatus);

			return;
		}

		try
		{
			$status = @json_decode($response->getBody()->getContents() ?? '{}');
		}
		catch (\Exception $e)
		{
			$this->logger->error(
				sprintf(
					'Extension updates for site #%d (%s): failed installing update for %s “%s”. Invalid JSON reply: %s',
					$site->id, $site->name, $extensions[$extensionId]->type, $extensions[$extensionId]->name,
					$response->getBody()
				)
			);

			$updateStatus[$extensionId] = [
				'type'     => $extensions[$extensionId]->type,
				'name'     => $extensions[$extensionId]->name,
				'status'   => 'invalid_json',
				'messages' => [$e->getMessage()],
			];
			$storage->set('updateStatus', $updateStatus);

			return;
		}

		if (!$status->attributes?->status ?? 1)
		{
			$this->logger->error(
				sprintf(
					'Extension updates for site #%d (%s): failed installing update for %s “%s”. Joomla! reported an error: %s',
					$site->id, $site->name, $extensions[$extensionId]->type, $extensions[$extensionId]->name,
					implode(' • ', $status->attributes?->messages ?? [])
				),
				(array) $status
			);

			$updateStatus[$extensionId] = [
				'type'     => $extensions[$extensionId]->type,
				'name'     => $extensions[$extensionId]->name,
				'status'   => 'error',
				'messages' => $status->attributes?->messages ?? [],
			];
			$storage->set('updateStatus', $updateStatus);

			return;
		}

		$this->logger->debug(
			sprintf(
				'Extension updates for site #%d (%s): installed update for %s “%s”',
				$site->id, $site->name, $extensions[$extensionId]->type, $extensions[$extensionId]->name
			),
			(array) $status
		);

		// Update extensions.list and extensions.hasUpdates in the site's config storage
		try
		{
			// Ensure the site information read/write is an atomic operation
			$this->container->db->lockTable('#__sites');

			// Reload the site information, in case it changed while we were installing updates
			$site->findOrFail($site->id);

			// Get the extensions list
			$siteConfig = ($site->getFieldValue('config') instanceof Registry)
				? $site->getFieldValue('config')
				: (new Registry($site->getFieldValue('config')));
			$extensions = (array)$siteConfig->get('extensions.list');

			// Make sure our updated extension didn't get uninstalled in the meantime
			if (!isset($extensions[$extensionId]))
			{
				throw new \RuntimeException('The extension went away.');
			}

			// Mark the extension as not having updates
			$extensions[$extensionId]->version->new = null;
			$siteConfig->set('extensions.list', $extensions);

			// Set a flag for the existence of updates
			$hasUpdates    = array_reduce(
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

			// Save the modified site
			$site->save();
		}
		catch (\Throwable)
		{
			// No worries, this was a mostly optional step...
		}
		finally
		{
			// No matter what happens, we need to unlock the tables.
			$this->container->db->unlockTables();
		}

		$updateStatus[$extensionId] = [
			'type'     => $extensions[$extensionId]->type,
			'name'     => $extensions[$extensionId]->name,
			'status'   => 'success',
			'messages' => $status->attributes?->messages ?? [],
		];
		$storage->set('updateStatus', $updateStatus);
	}

	private function enqueueEmail(Site $site, Registry $storage): void
	{
		// Render the messages as HTML
		$updateStatus            = (array) $storage->get('updateStatus', []);

		if (empty($updateStatus))
		{
			// We did not do anything. No need to email the user to tell them we did sod all.
			return;
		}

		$language                = 'en-GB';
		$possibleTemplates       = [
			'Mailtemplates/mail_extensions_update_done.' . $language,
			'Mailtemplates/mail_extensions_update_done',
		];
		$container               = clone $this->container;
		$container['mvc_config'] = [
			'template_path' => [
				APATH_ROOT . '/ViewTemplates/Mailtemplates',
				APATH_USER_CODE . '/ViewTemplates/Mailtemplates',
			],
		];
		$fakeView                = new Html($container);
		$rendered                = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$rendered = $fakeView->loadAnyTemplate($template, [
					'updateStatus' => $updateStatus,
					'site'         => $site,
				]);
			}
			catch (\Exception $e)
			{
				// Expected, as the language override may not be in place.
			}
		}

		// Render the messages as plain text
		$possibleTemplates = [
			'Mailtemplates/mail_extensions_update_done.' . $language . 'text',
			'Mailtemplates/mail_extensions_update_done.text',
		];
		$renderedText      = '';

		foreach ($possibleTemplates as $template)
		{
			try
			{
				$renderedText = $fakeView->loadAnyTemplate($template, [
					'updateStatus' => $updateStatus,
					'site'         => $site,
				]);
			}
			catch (\Exception $e)
			{
				// Expected, as the language override may not be in place.
			}
		}

		// Fall back to automatic HTML to plain text conversion
		if (empty($renderedText))
		{
			$renderedText = (new Html2Text($rendered))->getText();
		}

		$emailKey  = 'extensions_update_done';
		$variables = [
			'[SITE_NAME]'     => $site->name,
			'[RENDERED_HTML]' => $rendered,
			'[RENDERED_TEXT]' => $renderedText,
		];

		// Get the CC email addresses
		$cc = array_map(
			function (string $item)
			{
				$item = trim($item);

				if (!str_contains($item, '<'))
				{
					return [$item, ''];
				}

				[$name, $email] = explode('<', $item, 2);
				$name  = trim($name);
				$email = trim(
					str_contains($email, '>')
						? substr($email, 0, strrpos($email, '>') - 1)
						: $email
				);

				return [$email, $name];
			},
			explode(',', $config?->config?->core_update?->email?->cc ?? "")
		);

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin']);
		$data->set('email_cc', $cc);

		$queueItem = new QueueItem(
			$data->toString(),
			QueueTypeEnum::MAIL->value,
			$site->id
		);
		$queue     = $this->container->queueFactory->makeQueue(QueueTypeEnum::MAIL->value);

		$queue->push($queueItem, 'now');
	}

	private function reloadExtensionInformation(Site $site): void
	{
		$this->logger->info(sprintf(
			'Refreshing the extension update information for site #%d (%s)',
			$site->id,
			$site->name
		));

		$callback = $this->container->taskRegistry->get('refreshinstalledextensions');

		$dummy         = new \stdClass();
		$dummyRegistry = new Registry();

		$dummyRegistry->set('limitStart', 0);
		$dummyRegistry->set('limit', 10);
		$dummyRegistry->set('force', true);
		$dummyRegistry->set('filter.ids', [$site->id]);

		$return = $callback($dummy, $dummyRegistry);
	}
}