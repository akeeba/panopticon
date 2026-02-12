<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

use Akeeba\Panopticon\Helper\Html2Text;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\View\FakeView;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Awf\Registry\Registry;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

defined('AKEEBA') || die;

#[AsTask(
	name: 'extensioninstall',
	description: 'PANOPTICON_TASKTYPE_EXTENSIONINSTALL'
)]
class ExtensionInstall extends AbstractCallback
{
	use ApiRequestTrait;
	use EmailSendingTrait;
	use JsonSanitizerTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$params = ($task->params instanceof Registry)
			? $task->params
			: new Registry($task->params);

		$siteIds        = $params->get('sites', []);
		$url            = $params->get('url', '');
		$filePath       = $params->get('file', '');
		$initiatingUser = $params->get('initiating_user', 0);

		$results      = (array) $storage->get('results', []);
		$currentIndex = (int) $storage->get('currentIndex', 0);

		if (empty($siteIds))
		{
			$this->logger->warning('No sites specified for extension installation.');

			return Status::OK->value;
		}

		// Find the next unprocessed site
		if ($currentIndex >= count($siteIds))
		{
			// All sites processed — send summary and finish
			return $this->finalize($task, $storage, $siteIds, $results, $filePath, $initiatingUser);
		}

		$siteId = (int) $siteIds[$currentIndex];

		$this->logger->info(sprintf(
			'Processing site %d (%d of %d)',
			$siteId, $currentIndex + 1, count($siteIds)
		));

		// Load site
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (\Exception $e)
		{
			$this->logger->error(sprintf('Site %d not found: %s', $siteId, $e->getMessage()));

			$results[$siteId] = [
				'site_name' => 'Unknown (ID: ' . $siteId . ')',
				'status'    => 'failed',
				'message'   => 'Site not found.',
			];

			$storage->set('results', $results);
			$storage->set('currentIndex', $currentIndex + 1);

			return Status::WILL_RESUME->value;
		}

		// Push site-specific logger
		$this->logger->pushLogger(
			$this->container->loggerFactory->get($this->name . '.' . $site->id)
		);

		// Perform the installation
		try
		{
			$result = $this->installOnSite($site, $url, $filePath);

			$results[$siteId] = [
				'site_name' => $site->name,
				'status'    => $result['status'],
				'message'   => $result['message'],
			];

			// Refresh the installed extensions list after a successful install
			if ($result['status'] === 'success')
			{
				$this->refreshInstalledExtensions($site);
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error(sprintf(
				'Exception installing on site %d (%s): %s',
				$siteId, $site->name, $e->getMessage()
			));

			$results[$siteId] = [
				'site_name' => $site->name,
				'status'    => 'failed',
				'message'   => $e->getMessage(),
			];
		}

		$storage->set('results', $results);
		$storage->set('currentIndex', $currentIndex + 1);

		return Status::WILL_RESUME->value;
	}

	/**
	 * Perform the extension installation on a single site.
	 *
	 * @param   Site    $site      The site to install on
	 * @param   string  $url       Download URL (empty if using file upload)
	 * @param   string  $filePath  Local file path (empty if using URL)
	 *
	 * @return  array{status: string, message: string}
	 */
	private function installOnSite(Site $site, string $url, string $filePath): array
	{
		$installPath = $this->getInstallPath($site);
		$httpClient  = $this->container->httpFactory->makeClient(cache: false);

		if (!empty($url))
		{
			// URL-based installation (POST)
			[$apiUrl, $options] = $this->getRequestOptions($site, $installPath);

			$options[RequestOptions::FORM_PARAMS] = [
				'url' => $url,
			];

			$this->logger->info(sprintf('Installing from URL on %s: %s', $site->name, $url));

			try
			{
				$response = $httpClient->post($apiUrl, $options);
			}
			catch (GuzzleException $e)
			{
				return $this->handleHttpException($e, $site);
			}
		}
		elseif (!empty($filePath) && is_file($filePath))
		{
			// File-based installation (PUT)
			$filename = basename($filePath);
			[$apiUrl, $options] = $this->getRequestOptions($site, $installPath . '?filename=' . urlencode($filename));

			$options[RequestOptions::BODY]                    = file_get_contents($filePath);
			$options[RequestOptions::HEADERS]['Content-Type'] = 'application/octet-stream';

			$this->logger->info(sprintf('Installing from file on %s: %s', $site->name, $filename));

			try
			{
				$response = $httpClient->put($apiUrl, $options);
			}
			catch (GuzzleException $e)
			{
				return $this->handleHttpException($e, $site);
			}
		}
		else
		{
			return [
				'status'  => 'failed',
				'message' => 'No URL or file path provided.',
			];
		}

		return $this->parseResponse($response, $site);
	}

	/**
	 * Parse the API response from the remote site.
	 *
	 * @param   \Psr\Http\Message\ResponseInterface  $response  The HTTP response
	 * @param   Site                                  $site      The site
	 *
	 * @return  array{status: string, message: string}
	 */
	private function parseResponse($response, Site $site): array
	{
		$statusCode = $response->getStatusCode();
		$body       = $this->sanitizeJson($response->getBody()->getContents());

		$this->logger->debug(sprintf('Response from %s: HTTP %d', $site->name, $statusCode));

		$data = @json_decode($body);

		if ($data === null)
		{
			return [
				'status'  => 'failed',
				'message' => 'Invalid JSON response from the site (HTTP ' . $statusCode . ').',
			];
		}

		// Check for JSON:API error response
		if (isset($data->errors) && is_array($data->errors))
		{
			$errorTitle = $data->errors[0]->title ?? $data->errors[0]->message ?? 'Unknown error';
			$errorCode  = $data->errors[0]->code ?? $statusCode;

			// Check for "remote install disabled" error
			if (
				str_contains(strtolower($errorTitle), 'remote extension installation is disabled')
				|| str_contains(strtolower($errorTitle), 'remote_install_disabled')
			)
			{
				return [
					'status'  => 'disabled',
					'message' => $errorTitle,
				];
			}

			return [
				'status'  => 'failed',
				'message' => $errorTitle . ' (code: ' . $errorCode . ')',
			];
		}

		// WordPress WP_Error format
		if (isset($data->code) && isset($data->message))
		{
			if ($data->code === 'remote_install_disabled')
			{
				return [
					'status'  => 'disabled',
					'message' => $data->message,
				];
			}

			return [
				'status'  => 'failed',
				'message' => $data->message . ' (code: ' . $data->code . ')',
			];
		}

		// JSON:API success format: {data: {attributes: {status: true, messages: [...]}}}
		$attributes = $data->data->attributes ?? null;

		if ($attributes !== null)
		{
			$installStatus = $attributes->status ?? false;
			$messages      = $attributes->messages ?? [];
			$messageText   = '';

			if (is_array($messages) && !empty($messages))
			{
				$messageTexts = array_map(function ($msg) {
					if (is_object($msg))
					{
						return ($msg->message ?? '');
					}
					if (is_array($msg))
					{
						return ($msg['message'] ?? '');
					}

					return (string) $msg;
				}, $messages);

				$messageText = implode('; ', array_filter($messageTexts));
			}

			return [
				'status'  => $installStatus ? 'success' : 'failed',
				'message' => $messageText ?: ($installStatus ? 'Installed successfully.' : 'Installation returned failure status.'),
			];
		}

		// Fallback: check HTTP status code
		if ($statusCode >= 200 && $statusCode < 300)
		{
			return [
				'status'  => 'success',
				'message' => 'Installation completed (HTTP ' . $statusCode . ').',
			];
		}

		return [
			'status'  => 'failed',
			'message' => 'Unexpected response (HTTP ' . $statusCode . ').',
		];
	}

	/**
	 * Handle an HTTP exception from a Guzzle request.
	 *
	 * @param   GuzzleException  $e     The exception
	 * @param   Site             $site  The site
	 *
	 * @return  array{status: string, message: string}
	 */
	private function handleHttpException(GuzzleException $e, Site $site): array
	{
		$statusCode = 0;

		if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse())
		{
			$response   = $e->getResponse();
			$statusCode = $response->getStatusCode();
			$body       = $this->sanitizeJson($response->getBody()->getContents());
			$data       = @json_decode($body);

			// Check for "disabled" response
			if ($statusCode === 403)
			{
				$message = $data->errors[0]->title ?? $data->message ?? $e->getMessage();

				if (
					str_contains(strtolower($message), 'remote extension installation is disabled')
					|| ($data->code ?? '') === 'remote_install_disabled'
				)
				{
					return [
						'status'  => 'disabled',
						'message' => $message,
					];
				}
			}

			// Extract error message from JSON:API or WP_Error format
			if (isset($data->errors[0]->title))
			{
				return [
					'status'  => 'failed',
					'message' => $data->errors[0]->title . ' (HTTP ' . $statusCode . ')',
				];
			}

			if (isset($data->message))
			{
				return [
					'status'  => 'failed',
					'message' => $data->message . ' (HTTP ' . $statusCode . ')',
				];
			}
		}

		return [
			'status'  => 'failed',
			'message' => $e->getMessage() . ($statusCode ? ' (HTTP ' . $statusCode . ')' : ''),
		];
	}

	/**
	 * Get the API path for the extension install endpoint based on CMS type.
	 *
	 * @param   Site  $site  The site
	 *
	 * @return  string
	 */
	private function getInstallPath(Site $site): string
	{
		return match ($site->cmsType())
		{
			CMSType::JOOMLA   => '/index.php/v1/panopticon/extension/install',
			CMSType::WORDPRESS => '/v1/panopticon/extension/install',
			default           => '/v1/panopticon/extension/install',
		};
	}

	/**
	 * Refresh the installed extensions list for a site after a successful installation.
	 *
	 * @param   Site  $site  The site to refresh
	 *
	 * @return  void
	 */
	private function refreshInstalledExtensions(Site $site): void
	{
		$this->logger->info(sprintf(
			'Refreshing installed extensions list for site #%d (%s)',
			$site->id, $site->name
		));

		try
		{
			$callback = $this->container->taskRegistry->get('refreshinstalledextensions');

			$dummy         = new \stdClass();
			$dummyRegistry = new Registry();

			$dummyRegistry->set('limitStart', 0);
			$dummyRegistry->set('limit', 10);
			$dummyRegistry->set('force', true);
			$dummyRegistry->set('filter.ids', [$site->id]);

			$callback($dummy, $dummyRegistry);
		}
		catch (\Throwable $e)
		{
			$this->logger->warning(sprintf(
				'Failed to refresh extensions for site #%d (%s): %s',
				$site->id, $site->name, $e->getMessage()
			));
		}
	}

	/**
	 * Finalize the task: send summary email and disable the task.
	 *
	 * @param   object    $task           The task object
	 * @param   Registry  $storage        Task storage
	 * @param   array     $siteIds        The site IDs
	 * @param   array     $results        The results array
	 * @param   string    $filePath       The uploaded file path (for cleanup)
	 * @param   int       $initiatingUser The user ID who initiated the installation
	 *
	 * @return  int
	 */
	private function finalize(
		object $task, Registry $storage, array $siteIds, array $results,
		string $filePath, int $initiatingUser
	): int
	{
		$this->logger->info('All sites processed. Sending summary email.');

		// Count results
		$successCount  = 0;
		$failCount     = 0;
		$disabledCount = 0;

		foreach ($results as $key => $result)
		{
			$result = (array) $result;
			$results[$key] = $result;

			match ($result['status'] ?? 'failed')
			{
				'success'  => $successCount++,
				'disabled' => $disabledCount++,
				default    => $failCount++,
			};
		}

		// Pre-render the Blade templates into strings
		$totalCount    = count($siteIds);
		$templateVars  = [
			'RESULTS'        => $results,
			'SUCCESS_COUNT'  => $successCount,
			'FAIL_COUNT'     => $failCount,
			'DISABLED_COUNT' => $disabledCount,
			'TOTAL_COUNT'    => $totalCount,
		];

		$container               = clone $this->container;
		$container['mvc_config'] = [
			'template_path' => [
				APATH_ROOT . '/ViewTemplates/Mailtemplates',
				APATH_USER_CODE . '/ViewTemplates/Mailtemplates',
			],
		];
		$fakeView = new FakeView($container, ['name' => 'Mailtemplates']);

		$rendered     = '';
		$renderedText = '';

		try
		{
			$rendered = $fakeView->loadAnyTemplate(
				'Mailtemplates/mail_extension_install_summary',
				$templateVars
			);
		}
		catch (\Exception $e)
		{
			$this->logger->error('Failed to render HTML email template: ' . $e->getMessage());
		}

		try
		{
			$renderedText = $fakeView->loadAnyTemplate(
				'Mailtemplates/mail_extension_install_summary.text',
				$templateVars
			);
		}
		catch (\Exception $e)
		{
			$renderedText = (new Html2Text($rendered))->getText();
		}

		// Send summary email
		$data = new Registry();
		$data->set('template', 'extension_install_summary');
		$data->set('email_variables', [
			'RENDERED_HTML'  => $rendered,
			'RENDERED_TEXT'  => $renderedText,
			'SUCCESS_COUNT'  => $successCount,
			'FAIL_COUNT'     => $failCount,
			'DISABLED_COUNT' => $disabledCount,
			'TOTAL_COUNT'    => $totalCount,
		]);

		// Send to the initiating user
		if ($initiatingUser > 0)
		{
			$data->set('recipient_id', $initiatingUser);
		}
		else
		{
			$data->set('permissions', ['panopticon.super', 'panopticon.admin']);
		}

		$this->enqueueEmail($data, null, 'now');

		// Clean up temp file
		if (!empty($filePath) && is_file($filePath))
		{
			@unlink($filePath);
			$this->logger->info(sprintf('Cleaned up temp file: %s', $filePath));
		}

		// Delete the task to avoid accumulation
		if ($task instanceof \Akeeba\Panopticon\Model\Task)
		{
			$task->delete();
		}

		$this->logger->info(sprintf(
			'Extension installation complete: %d succeeded, %d failed, %d disabled',
			$successCount, $failCount, $disabledCount
		));

		return Status::OK->value;
	}
}
