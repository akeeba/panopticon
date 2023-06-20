<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Awf\Mvc\Model;
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

	private Site $site;

	public function __invoke(object $task, Registry $storage): int
	{
		// Get the site object
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->site = Model::getTmpInstance(null, 'Site', $this->container);
		$this->site->findOrFail($task->site_id);

		// Add a site-specific logger
		$this->logger->pushLogger($this->container->loggerFactory->get($this->name . '.' . $this->site->id));

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
		$session = (array) $result->attributes?->session ?? null;
		$storage->set('session', $session);

		foreach ($result->attributes?->warnings ?? [] as $warning)
		{
			$this->logger->warning($warning);
		}

		if ($result->attributes?->error)
		{
			throw new \RuntimeException($result->attributes?->error);
		}

		if ($result->attributes?->done)
		{
			$this->logger->info(
				sprintf(
					'PHP File Change Scanner has finished scanning site #%d (%s).',
					$this->site->getId(),
					$this->site->name,
				)
			);

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

	private function startScan(): ?object
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this->site, '/index.php/v1/panopticon/admintools/scanner/start');

		$response = $httpClient->post($url, $options);

		$json = $response->getBody()->getContents();

		$this->logger->debug('Got response', ['body' => $json]);

		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			throw new \RuntimeException($error->title, $error->code);
		}

		return $result?->data ?? null;
	}

	private function stepScan(array|object $session): ?object
	{
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		[$url, $options] = $this->getRequestOptions($this->site, '/index.php/v1/panopticon/admintools/scanner/step');
		$options[RequestOptions::FORM_PARAMS]['session'] = (array) $session;

		$response = $httpClient->post($url, $options);

		$json = $response->getBody()->getContents();

		$this->logger->debug('Got response', ['body' => $json]);

		$result = json_decode($json);

		if ($result?->errors ?? null)
		{
			$error = array_pop($result->errors);

			throw new \RuntimeException($error->title, $error->code);
		}

		return $result?->data ?? null;
	}
}