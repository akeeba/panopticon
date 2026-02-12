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
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Awf\Registry\Registry;

#[AsTask(
	name: 'corechecksums',
	description: 'PANOPTICON_TASKTYPE_CORECHECKSUMS'
)]
class CoreChecksums extends AbstractCallback
{
	use ApiRequestTrait;
	use JsonSanitizerTrait;
	use EmailSendingTrait;
	use SaveSiteTrait;

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

		// Only Joomla sites are supported
		if ($this->site->cmsType() !== CMSType::JOOMLA)
		{
			$this->logger->error(
				sprintf(
					'Site #%d (%s) is not a Joomla site. Core file integrity checks are only supported for Joomla sites.',
					$this->site->getId(),
					$this->site->name
				)
			);

			throw new \RuntimeException('Core file integrity checks are only supported for Joomla sites.');
		}

		// Load the temporary storage
		$state        = $storage->get('state', 'init');
		$lastId       = $storage->get('last_id', 0);
		$invalidFiles = $storage->get('invalidFiles', []);

		if ($state === 'init')
		{
			$this->logger->info(
				sprintf(
					'Starting core file integrity check on site #%d (%s)',
					$this->site->getId(),
					$this->site->name,
				)
			);

			$result = $this->prepareScan();

			if ($result === null)
			{
				$this->logFailedReport($initiatingUser);

				$this->logger->error('Invalid response from the remote server during prepare step.');

				throw new \RuntimeException('Invalid response from the remote server during prepare step.');
			}

			$storage->set('state', 'step');
			$storage->set('last_id', 0);
			$storage->set('invalidFiles', []);

			$this->logger->info('Prepare step complete, moving to file checking.');

			return Status::WILL_RESUME->value;
		}

		// Step
		$this->logger->info(
			sprintf(
				'Continuing core file integrity check on site #%d (%s), last_id=%d',
				$this->site->getId(),
				$this->site->name,
				$lastId,
			)
		);

		$result = $this->stepScan($lastId);

		if ($result === null || !is_object($result))
		{
			$this->logFailedReport($initiatingUser);

			$this->logger->error(
				sprintf(
					'Invalid response from the remote server (%s)',
					empty($result) ? 'no data' : 'response is not an object'
				)
			);

			throw new \RuntimeException('Invalid response from the remote server.');
		}

		// Accumulate invalid files
		$newInvalid   = (array) ($result->invalidFiles ?? []);
		$invalidFiles = array_merge($invalidFiles, $newInvalid);

		if (!empty($newInvalid))
		{
			$this->logger->warning(
				sprintf('Found %d modified core files in this step.', count($newInvalid))
			);
		}

		$storage->set('last_id', (int) ($result->last_id ?? 0));
		$storage->set('invalidFiles', $invalidFiles);

		if ($result->done ?? false)
		{
			$this->logger->info(
				sprintf(
					'Core file integrity check has finished on site #%d (%s). Modified files: %d',
					$this->site->getId(),
					$this->site->name,
					count($invalidFiles),
				)
			);

			// Save results to site config
			$this->saveSite(
				$this->site,
				function (Site $site) use ($invalidFiles)
				{
					$config = $site->getConfig();
					$config->set('core.coreChecksums.modifiedFiles', $invalidFiles);
					$config->set('core.coreChecksums.modifiedCount', count($invalidFiles));
					$config->set('core.coreChecksums.lastCheck', time());
					$config->set('core.coreChecksums.lastStatus', empty($invalidFiles));
					$site->config = $config;
				}
			);

			// Log report
			$this->logReport(empty($invalidFiles), $initiatingUser, $invalidFiles);

			// Send email notification if modified files found
			if (!empty($invalidFiles))
			{
				$this->sendNotificationEmail($this->site, $invalidFiles);
			}

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'More work for core file integrity check on site #%d (%s).',
				$this->site->getId(),
				$this->site->name,
			)
		);

		return Status::WILL_RESUME->value;
	}

	private function prepareScan(): mixed
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		[$url, $options] = $this->getRequestOptions(
			$this->site, '/index.php/v1/panopticon/core/checksum/prepare'
		);

		$response = $httpClient->get($url, $options);

		$json = $this->sanitizeJson($response->getBody()->getContents());

		$this->logger->debug('Got prepare response', ['body' => $json]);

		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			$this->logger->error($error->title ?? $error->message ?? 'Unknown error');

			throw new \RuntimeException($error->title ?? $error->message ?? 'Unknown error', $error->code ?? 500);
		}

		return $result;
	}

	private function stepScan(int $lastId): ?object
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		[$url, $options] = $this->getRequestOptions(
			$this->site,
			sprintf('/index.php/v1/panopticon/core/checksum/step/%d', $lastId)
		);

		$response = $httpClient->get($url, $options);

		$json = $this->sanitizeJson($response->getBody()->getContents());

		$this->logger->debug('Got step response', ['body' => $json]);

		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			$this->logger->error($error->title ?? $error->message ?? 'Unknown error');

			throw new \RuntimeException($error->title ?? $error->message ?? 'Unknown error', $error->code ?? 500);
		}

		return $result;
	}

	private function logReport(bool $status, int $initiatingUser, array $invalidFiles = []): void
	{
		try
		{
			$context = [
				'modifiedCount' => count($invalidFiles),
			];

			if (!empty($invalidFiles))
			{
				$context['modifiedFiles'] = $invalidFiles;
			}

			$report = Reports::fromCoreChecksums(
				$this->site->id,
				$status,
				$context
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
	}

	private function logFailedReport(int $initiatingUser): void
	{
		try
		{
			$report = Reports::fromCoreChecksums(
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
	}

	private function sendNotificationEmail(Site $site, array $invalidFiles): void
	{
		$vars = [
			'SITE_NAME'      => $site->name,
			'SITE_URL'       => $site->getBaseUrl(),
			'SITE_ID'        => $site->getId(),
			'MODIFIED_COUNT' => count($invalidFiles),
			'MODIFIED_FILES' => implode("\n", $invalidFiles),
		];

		$data = new Registry();
		$data->set('template', 'core_checksums_found');
		$data->set('email_variables', $vars);
		$data->set('permissions', ['panopticon.admin', 'panopticon.editown']);

		$this->logger->debug('Sending core checksums notification email', $data->toArray());

		$this->enqueueEmail($data, $site->getId(), 'now');
	}
}
