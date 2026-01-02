<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
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
use stdClass;
use Throwable;

trait SiteTestConnectionJoomlaTrait
{
	public function testConnectionJoomla(bool $getWarnings = true): array
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->container;
		$client    = $container->httpFactory->makeClient(cache: false, singleton: false);

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

		// Try to get index.php/v1/extensions unauthenticated
		try
		{
			$totalTimeout   = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
			$connectTimeout = max(5, $totalTimeout / 5);

			$options                                  = $container->httpFactory->getDefaultRequestOptions();
			$options[RequestOptions::HEADERS]         = [
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			];
			$options[RequestOptions::HTTP_ERRORS]     = false;
			$options[RequestOptions::CONNECT_TIMEOUT] = $connectTimeout;
			$options[RequestOptions::TIMEOUT]         = $totalTimeout;

			$session->set('testconnection.step', 'Unauthenticated access (can I even access the API at all?)');

			[$url,] = $this->getRequestOptions($this, '/index.php/v1/extensions');

			$response = $client->get($url, $options);
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

		$bodyContent ??= $response?->getBody()?->getContents();
		$this->updateDebugInfoInSession($response ?? null, $bodyContent, $e ?? null);

		if (!isset($response))
		{
			throw new RuntimeException('No response to the unauthenticated API request probe.', 500);
		}

		if ($response->getStatusCode() === 403)
		{
			throw new APIApplicationIsBlocked('The API application is blocked (403)');
		}
		elseif ($response->getStatusCode() === 404)
		{
			throw new WebServicesInstallerNotEnabled(
				'Cannot list installed extensions. Web Services - Installer is not enabled.'
			);
		}
		elseif ($response->getStatusCode() !== 401)
		{
			$this->container->segment->setFlash('site_connection_http_code', $response->getStatusCode());

			if (!str_contains($bodyContent, '{"errors":[{"title":"Forbidden"}]}'))
			{
				throw new APIApplicationIsBroken(
					sprintf('The API application does not work property (HTTP %d)', $response->getStatusCode())
				);
			}

			$canWorkAround = $this->jsonValidate($this->sanitizeJson($bodyContent));

			if (!$canWorkAround)
			{
				throw new APIApplicationHasPHPMessages();
			}
		}

		// Try to access index.php/v1/extensions **authenticated**
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/extensions?page[limit]=2000');
		$options[RequestOptions::HTTP_ERRORS] = false;

		$session->set('testconnection.step', 'Authenticated access (can I get information out of the API?)');

		try
		{
			$response    = $client->get($url, $options);
			$bodyContent = $response?->getBody()?->getContents();
		}
		catch (GuzzleException $e)
		{
			$this->updateDebugInfoInSession($response ?? null, $bodyContent, $e);

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
				'Cannot list installed extensions. Web Services - Installer is not enabled.'
			);
		}
		elseif ($response->getStatusCode() === 401)
		{
			try
			{
				$temp = @json_decode($this->sanitizeJson($bodyContent), true);
			}
			catch (Exception)
			{
				$temp = null;
			}

			if (
				is_array($temp) && isset($temp['errors']) && is_array($temp['errors'])
				&& isset($temp['errors'][0])
				&& is_array($temp['errors'][0])
				&& isset($temp['errors'][0]['code'])
				&& $temp['errors'][0]['code'] == 401
			)
			{
				throw new APIInvalidCredentials('The API Token is invalid');
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
		}
		catch (Throwable)
		{
			$results = new stdClass();
		}

		if (empty($results?->data))
		{
			throw new WebServicesInstallerNotEnabled(
				'Cannot list installed extensions. Web Services - Installer is not enabled.'
			);
		}

		// Check if Panopticon is enabled
		$allEnabled = array_reduce(
			array_filter(
				$results->data,
				fn(object $data) => str_contains($data->attributes?->name ?? '', 'Panopticon')
			),
			fn(bool $carry, object $data) => $carry
			                                 && (($data->attributes?->status ?? null) == 1
			                                     || $data->attributes?->enabled == 1),
			true
		);

		if (!$allEnabled)
		{
			throw new PanopticonConnectorNotEnabled('The Panopticon Connector component or plugin is not enabled');
		}

		if (!$getWarnings)
		{
			return [];
		}

		$warnings = [];

		// Check if Akeeba Backup and its API plugin are enabled
		$allEnabled = array_reduce(
			array_filter(
				$results->data,
				fn(object $data) => str_contains($data->attributes?->name ?? '', 'Akeeba Backup')
				                    && (
					                    $data->attributes?->type === 'component'
					                    || (
						                    $data->attributes?->type === 'plugin'
						                    && $data->attributes?->folder === 'webservices'
					                    )
				                    )
			),
			fn(bool $carry, object $data) => $carry && $data->attributes?->status == 1,
			true
		);

		if (!$allEnabled)
		{
			$warnings[] = 'akeebabackup';
		}

		// Check for Admin Tools component
		$allEnabled = array_reduce(
			array_filter(
				$results->data,
				fn(object $data) => str_contains($data->attributes?->name ?? '', 'Admin Tools')
				                    && (
					                    $data->attributes?->type === 'component'
					                    || (
						                    $data->attributes?->type === 'plugin'
						                    && $data->attributes?->folder === 'system'
					                    )
				                    )
			),
			fn(bool $carry, object $data) => $carry && $data->attributes?->status == 1,
			true
		);

		if (!$allEnabled)
		{
			$warnings[] = 'admintools';
		}


		$session->set('testconnection.step', null);
		$this->updateDebugInfoInSession(null, null, null);

		return $warnings;
	}

}