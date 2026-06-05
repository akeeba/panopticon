<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\AdminToolsTrait;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\LogAttachmentTrait;
use Akeeba\Panopticon\Task\Trait\ResponseLoggerTrait;
use Awf\Registry\Registry;
use GuzzleHttp\RequestOptions;

#[AsTask(
	name: 'filescanner',
	description: 'PANOPTICON_TASKTYPE_FILESCANNER'
)]
class FileScanner extends AbstractCallback
{
	use ApiRequestTrait;
	use AdminToolsTrait;
	use JsonSanitizerTrait;
	use ResponseLoggerTrait;
	use EmailSendingTrait;
	use LogAttachmentTrait;

	private Site $site;

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->site = $this->container->mvcFactory->makeTempModel('Site');
		$this->site->findOrFail($task->site_id);

		// Load the task configuration parameters
		$params         = $task->params instanceof Registry ? $task->params : new Registry($task->params);
		$initiatingUser = $params->get('initiatingUser', 0);

		// Add a site-specific logger
		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $this->site->id));

		try
		{
		// Is Admin Tools Professional installed?
		if (!($this->hasAdminTools($this->site, true)))
		{
			$this->logger->error(
				sprintf(
					'Site #%d (%s) does not seem to have Admin Tools Professional installed.',
					$this->site->getId(),
					$this->site->name
				)
			);

			throw new \RuntimeException('The site does not seem to have Admin Tools Professional installed.');
		}

		// Load the temporary storage
		$state   = $storage->get('state', 'init');
		$session = $storage->get('session', null);

		// Run the backup start or step, depending on our state
		if ($state === 'init')
		{
			$this->logger->info(
				sprintf(
					'Starting PHP File Change Scanner on site #%d (%s)',
					$this->site->getId(),
					$this->site->name,
				)
			);

			$result = $this->startScan();
		}
		else
		{
			$this->logger->info(
				sprintf(
					'Continuing PHP File Change Scanner on site #%d (%s)',
					$this->site->getId(),
					$this->site->name,
				)
			);

			$result = $this->stepScan($session);
		}

		if (empty($result) || !is_object($result))
		{
			// Log failed scan report
			try
			{
				$report = Reports::fromFileScanner(
					$this->site->id,
					false
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception)
			{
				// Whatever
			}

			$this->logger->error(
				sprintf(
					'Invalid response from the remote server (%s)',
					empty($result) ? 'no data' : 'response is not an object'
				)
			);

			throw new \RuntimeException('Invalid response from the remote server.');
		}

		if (($result->id ?? null) && $state === 'init')
		{
			$this->logger->info(
				sprintf(
					'Got scan ID: %d',
					(int) $result->id
				)
			);
		}

		$storage->set('state', 'step');
		$resultBody = match ($this->site->cmsType()) {
			CMSType::JOOMLA => $result->attributes ?? null,
			CMSType::WORDPRESS => $result ?? null,
			CMSType::UNKNOWN => null
		};
		$session    = (array) $resultBody?->session ?? null;
		$storage->set('session', $session);

		foreach ($resultBody?->warnings ?? [] as $warning)
		{
			$this->logger->warning($warning);
		}

		if ($resultBody?->error)
		{
			// Log failed scan report
			try
			{
				$report = Reports::fromFileScanner(
					$this->site->id,
					false,
					[
						'message' => $resultBody?->error
					]
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception)
			{
				// Whatever
			}

			$this->logger->error($resultBody?->error);

			throw new \RuntimeException($resultBody?->error);
		}

		if ($resultBody?->done)
		{
			$this->logger->info(
				sprintf(
					'PHP File Change Scanner has finished scanning site #%d (%s).',
					$this->site->getId(),
					$this->site->name,
				)
			);

			// Log successful scan report
			try
			{
				$report = Reports::fromFileScanner(
					$this->site->id,
					true
				);

				if ($initiatingUser)
				{
					$report->created_by = $initiatingUser;
				}

				$report->save();
			}
			catch (\Exception)
			{
				// Whatever
			}

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'More work for PHP File Change Scanner on site #%d (%s).',
				$this->site->getId(),
				$this->site->name,
			)
		);

		return Status::WILL_RESUME->value;
		}
		catch (\Throwable $e)
		{
			$this->sendFailureEmail($e);
			throw $e;
		}
	}

	private function sendFailureEmail(\Throwable $e): void
	{
		$logIdentifier = $this->name . '.' . $this->site->id;
		$logFileName   = $logIdentifier . '.log';

		$vars = [
			'SITE_NAME' => $this->site->name,
			'SITE_URL'  => $this->site->getBaseUrl(),
			'MESSAGE'   => $e->getMessage(),
			'LOG_URL'   => $this->getLogFileUrl($logFileName),
		];

		$data = new Registry();
		$data->set('template', 'filescanner_failed');
		$data->set('email_variables', $vars);
		$data->set('permissions', ['panopticon.admin', 'panopticon.editown']);
		$data->set('email_attachment', $this->getLogAttachmentPath($logIdentifier));
		$data->set('email_attachment_groups', $this->getLogAttachmentGroups($this->site));

		$this->logger->debug('Sending filescanner failure notification email');

		$this->enqueueEmail($data, $this->site->getId(), 'now');
	}

	private function startScan(): ?object
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$pathPrefix = $this->site->cmsType() === CMSType::JOOMLA ? '/index.php' : '';

		[$url, $options] = $this->getRequestOptions($this->site, $pathPrefix . '/v1/panopticon/admintools/scanner/start');

		$response = $httpClient->post($url, $options);

		$rawBody = $response->getBody()->getContents();

		$this->logger->debug('Got response', $this->formatResponseLog($response, $rawBody));

		$json   = $this->sanitizeJson($rawBody);
		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			$this->logger->error($error->title);

			throw new \RuntimeException($error->title, $error->code);
		}

		return match ($this->site->cmsType())
		{
			CMSType::JOOMLA => $result?->data ?? null,
			CMSType::WORDPRESS => $result ?? null,
			CMSType::UNKNOWN => null
		};
	}

	private function stepScan(array|object $session): ?object
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$pathPrefix = $this->site->cmsType() === CMSType::JOOMLA ? '/index.php' : '';

		[$url, $options] = $this->getRequestOptions($this->site, $pathPrefix . '/v1/panopticon/admintools/scanner/step');

		$options[RequestOptions::FORM_PARAMS]['session'] = (array) $session;

		$response = $httpClient->post($url, $options);

		$rawBody = $response->getBody()->getContents();

		$this->logger->debug('Got response', $this->formatResponseLog($response, $rawBody));

		$json   = $this->sanitizeJson($rawBody);
		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			$this->logger->error($error->title);

			throw new \RuntimeException($error->title, $error->code);
		}

		return match ($this->site->cmsType())
		{
			CMSType::JOOMLA => $result?->data ?? null,
			CMSType::WORDPRESS => $result ?? null,
			CMSType::UNKNOWN => null
		};
	}
}