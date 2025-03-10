<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationHasPHPMessages;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBlocked;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBroken;
use Akeeba\Panopticon\Exception\SiteConnection\APIInvalidCredentials;
use Akeeba\Panopticon\Exception\SiteConnection\cURLError;
use Akeeba\Panopticon\Exception\SiteConnection\FrontendPasswordProtection;
use Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName;
use Akeeba\Panopticon\Exception\SiteConnection\PanopticonConnectorNotEnabled;
use Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL;
use Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem;
use Akeeba\Panopticon\Exception\SiteConnection\WebServicesInstallerNotEnabled;
use Awf\Uri\Uri;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Throwable;

trait SiteTestConnectionWPTrait
{
	public function testConnectionWordPress(bool $getWarnings = true): array
	{
		$session = $this->getContainer()->segment;
		$session->set('testconnection.step', null);
		$session->set('testconnection.http_status', null);
		$session->set('testconnection.body', null);
		$session->set('testconnection.headers', null);
		$session->set('testconnection.exception.type', null);
		$session->set('testconnection.exception.message', null);
		$session->set('testconnection.exception.file', null);
		$session->set('testconnection.exception.line', null);
		$session->set('testconnection.exception.trace', null);

		// Try to get wp/v2/media unauthenticated
		$this->tryAccessWordPressJsonApiUnauthenticated();

		// Try to access wp/v2/posts **authenticated**
		$results = $this->tryAccessWordPressJsonApiAuthenticated();

		// Check if Panopticon is enabled
		$this->ensurePanopticonConnectorInstalled($results);

		$warnings = [];

		if ($getWarnings)
		{
			// Check if Akeeba Backup and its API plugin are enabled
			if (!$this->isAkeebaBackupProfessionalDetected($results))
			{
				$warnings[] = 'akeebabackup';
			}

			// Check for Admin Tools plugin
			if (!$this->isAdminToolsProfessionalDetected($results))
			{
				$warnings[] = 'admintools';
			}
		}

		$session->set('testconnection.step', null);
		$this->updateDebugInfoInSession(null, null, null);

		return $warnings;
	}

	private function tryAccessWordPressJsonApiUnauthenticated(string $path = '/wp/v2/media'): void
	{
		// Try to get wp/v2/media unauthenticated
		$session = $this->getContainer()->segment;

		try
		{
			$totalTimeout   = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
			$connectTimeout = max(5, $totalTimeout / 5);

			$options                                  = $this->container->httpFactory->getDefaultRequestOptions();
			$options[RequestOptions::HEADERS]         = [
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			];
			$options[RequestOptions::HTTP_ERRORS]     = false;
			$options[RequestOptions::CONNECT_TIMEOUT] = $connectTimeout;
			$options[RequestOptions::TIMEOUT]         = $totalTimeout;

			$session->set('testconnection.step', 'Unauthenticated access (can I even access the API at all?)');

			[$url,] = $this->getRequestOptions($this, $path);

			$response = $this->container->httpFactory->makeClient(cache: false, singleton: false)->get($url, $options);
		}
		catch (GuzzleException $e)
		{
			$this->updateDebugInfoInSession(null, null, $e);

			$message = $e->getMessage();

			if (str_contains($message, 'self-signed certificate'))
			{
				throw new SelfSignedSSL('Self-signed certificate', previous: $e);
			}

			if (str_contains($message, 'SSL certificate problem'))
			{
				throw new SSLCertificateProblem('SSL certificate problem', previous: $e);
			}

			if (str_contains($message, 'Could not resolve host'))
			{
				$hostname = empty($this->url) ? '(no host provided)' : (new Uri($this->url))->getHost();
				throw new InvalidHostName(sprintf('Invalid hostname %s', $hostname));
			}

			// DO NOT MOVE! We also use the same flash variable to report Guzzle errors
			$this->container->segment->setFlash('site_connection_curl_error', $e->getMessage());

			if (str_contains($message, 'cURL error'))
			{
				throw new cURLError('Miscellaneous cURL Error', previous: $e);
			}

			// If we have no response object something went _really_ wrong. Throw it back and let the front-end handle it.
			if (!isset($response))
			{
				$this->container->segment->setFlash('site_connection_guzzle_error', $e->getMessage());

				throw $e;
			}
		}

		$bodyContent = $bodyContent ?? $response?->getBody()?->getContents();
		$this->updateDebugInfoInSession($response ?? null, $bodyContent, $e ?? null);

		if (!isset($response))
		{
			throw new RuntimeException('No response to the unauthenticated API request probe.', 500);
		}

		if ($response->getStatusCode() === 403)
		{
			throw new APIApplicationIsBlocked('The WordPress JSON API (/wp-json) is blocked (HTTP 403)');
		}
		elseif ($response->getStatusCode() === 404)
		{
			throw new WebServicesInstallerNotEnabled(
				'The WordPress JSON API (/wp-json) is blocked (HTTP 404)'
			);
		}
		elseif ($response->getStatusCode() !== 200)
		{
			$this->container->segment->setFlash('site_connection_http_code', $response->getStatusCode());

			if (!str_contains($bodyContent, '"_links": {'))
			{
				throw new APIApplicationIsBroken(
					sprintf(
						'The API application does not work property (HTTP %d with invalid response)',
						$response->getStatusCode()
					)
				);
			}

			$canWorkAround = $this->jsonValidate($this->sanitizeJson($bodyContent));

			if (!$canWorkAround)
			{
				throw new APIApplicationHasPHPMessages();
			}
		}

		// Make sure the valid response *is* a JSON document.
		try
		{
			$decoded = @json_decode($bodyContent, true);
		}
		catch (Exception $e)
		{
			$decoded = null;
		}

		if ($decoded === null)
		{
			throw new APIApplicationIsBroken(
				'The API application does not work property (not a JSON response)'
			);
		}
	}

	private function tryAccessWordPressJsonApiAuthenticated(string $path = '/wp/v2/plugins?per_page=10000'): array
	{
		$session = $this->getContainer()->segment;

		[$url, $options] = $this->getRequestOptions($this, $path);
		$options[RequestOptions::HTTP_ERRORS] = false;

		$session->set('testconnection.step', 'Authenticated access (can I get information out of the API?)');

		try
		{
			$response    = $this->container->httpFactory->makeClient(cache: false, singleton: false)->get($url, $options);
			$bodyContent = $response?->getBody()?->getContents();
		}
		catch (GuzzleException $e)
		{
			$this->updateDebugInfoInSession($response ?? null, null, $e);

			throw $e;
		}

		$this->updateDebugInfoInSession($response ?? null, $bodyContent, $e ?? null);

		if (!isset($response))
		{
			throw new RuntimeException('No response to the authenticated API request probe.', 500);
		}

		if ($response->getStatusCode() === 403)
		{
			throw new APIApplicationIsBlocked('The API application is blocked (403)');
		}
		elseif ($response->getStatusCode() === 404)
		{
			throw new WebServicesInstallerNotEnabled(
				'Cannot list installed plugins.'
			);
		}
		elseif ($response->getStatusCode() === 401)
		{
			try
			{
				$temp = @json_decode($this->sanitizeJson($bodyContent), true);
			}
			catch (Exception $e)
			{
				$temp = null;
			}

			if (
				is_array($temp) && isset($temp['data']) && is_array($temp['data'])
				&& isset($temp['data']['status'])
				&& is_scalar($temp['data']['status'])
				&& $temp['data']['status'] == 401
			)
			{
				throw new APIInvalidCredentials(
					'The API Token is invalid, or you have not enabled the Panopticon Connector plugin on your site.'
				);
			}

			throw new FrontendPasswordProtection();
		}
		elseif ($response->getStatusCode() !== 200)
		{
			$this->container->segment->setFlash('site_connection_http_code', $response->getStatusCode());

			throw new APIApplicationIsBroken(
				sprintf('The API application does not work property (HTTP %d)', $response->getStatusCode())
			);
		}

		try
		{
			$results = @json_decode($this->sanitizeJson($bodyContent ?? '{}'));

			if (empty($results))
			{
				throw new WebServicesInstallerNotEnabled(
					'Cannot list installed plugins.'
				);
			}
		}
		catch (Throwable $e)
		{
			throw new WebServicesInstallerNotEnabled(
				'Cannot list installed plugins.'
			);
		}

		return $results;
	}

	private function ensurePanopticonConnectorInstalled(array $results): void
	{
		$allEnabled = array_reduce(
			array_filter(
				$results,
				fn(object $data) => str_contains($data->name ?? '', 'Panopticon')
			),
			fn(bool $carry, object $data) => $carry && ($data->status ?? null) == 'active',
			true
		);

		if (!$allEnabled)
		{
			throw new PanopticonConnectorNotEnabled('The Panopticon Connector plugin is not enabled');
		}
	}

	private function isAkeebaBackupProfessionalDetected(array $results): bool
	{
		// Check if Akeeba Backup and its API plugin are enabled
		return count(
			array_filter(
				$results,
				fn(object $data) => str_contains($data->name ?? '', 'Akeeba Backup Professional')
				                    && ($data->status ?? null) === 'active'
			)
		);
	}

	private function isAdminToolsProfessionalDetected(array $results): bool
	{
		// Check if Akeeba Backup and its API plugin are enabled
		return count(
			array_filter(
				$results,
				fn(object $data) => str_contains($data->name ?? '', 'Admin Tools Professional')
				                    && ($data->status ?? null) === 'active'
			)
		);
	}
}