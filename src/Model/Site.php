<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationDoesNotAuthenticate;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBlocked;
use Akeeba\Panopticon\Exception\SiteConnection\APIApplicationIsBroken;
use Akeeba\Panopticon\Exception\SiteConnection\APIInvalidCredentials;
use Akeeba\Panopticon\Exception\SiteConnection\cURLError;
use Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName;
use Akeeba\Panopticon\Exception\SiteConnection\PanopticonConnectorNotEnabled;
use Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL;
use Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem;
use Akeeba\Panopticon\Exception\SiteConnection\WebServicesInstallerNotEnabled;
use Akeeba\Panopticon\Task\ApiRequestTrait;
use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Uri\Uri;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use RuntimeException;

/**
 * Defines a site known to Panopticon
 *
 * @property int       $id              Task ID.
 * @property string    $name            The name of the site (user-visible).
 * @property string    $url             The URL to the site (with the /api part).
 * @property int       $enabled         Is this site enabled?
 * @property Date      $created_on      When was this site created?
 * @property int       $created_by      Who created this site?
 * @property null|Date $modified_on     When was this site last modified?
 * @property null|int  $modified_by     Who last modified this site?
 * @property null|Date $locked_on       When was this site last locked for writing?
 * @property null|int  $locked_by       Who last locked this site for writing?
 * @property Registry  $config          The configuration for this site.
 *
 * @since  1.0.0
 */
class Site extends DataModel
{
	use ApiRequestTrait;

	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__sites';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->fieldsSkipChecks[] = 'enabled';

		$this->addBehaviour('filters');
	}

	public function check()
	{
		$this->name = trim($this->name ?? '');

		if (empty($this->name))
		{
			throw new RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_TITLE'));
		}

		if (empty($this->url))
		{
			throw new RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_URL'));
		}

		parent::check();

		$this->url = $this->cleanUrl($this->url);

		return $this;
	}

	public function testConnection(bool $getWarnings = true): array
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->container;
		$client    = $container->httpFactory->makeClient(cache: false, singleton: false);

		// Try to get index.php/v1/extensions unauthenticated
		try
		{
			$totalTimeout   = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
			$connectTimeout = max(5, $totalTimeout / 5);

			$options = $container->httpFactory->getDefaultRequestOptions();
			$options[RequestOptions::HEADERS] = [
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			];
			$options[RequestOptions::HTTP_ERRORS] = false;
			$options[RequestOptions::CONNECT_TIMEOUT] = $connectTimeout;
			$options[RequestOptions::TIMEOUT] = $totalTimeout;

			$response = $client->get($this->url . '/index.php/v1/extensions', $options);
		}
		catch (GuzzleException $e)
		{
			$message = $e->getMessage();

			if (str_contains($message, 'self-signed certificate'))
			{
				throw new SelfSignedSSL('Self-signed certificate', previous: $e);
			}

			if (str_contains($message, 'SSL certificate problem'))
			{
				throw new SSLCertificateProblem('SSL certificate problem', previous: $e);
			}

			if (str_contains($message,'Could not resolve host'))
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
		}

		if ($response->getStatusCode() === 403)
		{
			throw new APIApplicationIsBlocked('The API application is blocked (403)');
		}
		elseif ($response->getStatusCode() === 404)
		{
			throw new WebServicesInstallerNotEnabled('Cannot list installed extensions. Web Services - Installer is not enabled.');
		}
		elseif ($response->getStatusCode() !== 401)
		{
			$this->container->segment->setFlash('site_connection_http_code', $response->getStatusCode());

			throw new APIApplicationIsBroken(sprintf('The API application does not work property (HTTP %d)', $response->getStatusCode()));
		}

		// Try to access index.php/v1/extensions **authenticated**
		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/extensions?page[limit]=2000');
		$options[RequestOptions::HTTP_ERRORS] = false;

		$response = $client->get($url, $options);

		if ($response->getStatusCode() === 403)
		{
			throw new APIApplicationIsBlocked('The API application is blocked (403)');
		}
		elseif ($response->getStatusCode() === 404)
		{
			throw new WebServicesInstallerNotEnabled('Cannot list installed extensions. Web Services - Installer is not enabled.');
		}
		elseif ($response->getStatusCode() === 401)
		{
			throw new APIInvalidCredentials('The API Token is invalid');
		}
		elseif ($response->getStatusCode() !== 200)
		{
			$this->container->segment->setFlash('site_connection_http_code', $response->getStatusCode());

			throw new APIApplicationIsBroken(sprintf('The API application does not work property (HTTP %d)', $response->getStatusCode()));
		}

		try
		{
			$results = @json_decode($response->getBody()->getContents() ?? '{}');
		}
		catch (\Throwable $e)
		{
			$results = new \stdClass();
		}

		if (empty($results?->data))
		{
			throw new WebServicesInstallerNotEnabled('Cannot list installed extensions. Web Services - Installer is not enabled.');
		}

		// Check if Panopticon is enabled
		$allEnabled = array_reduce(
			array_filter(
				$results->data,
				fn (object $data) => str_contains($data->attributes?->name ?? '', 'Panopticon')
			),
			fn(bool $carry, object $data) => $carry && $data->attributes?->status == 1,
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
				fn (object $data) => str_contains($data->attributes?->name ?? '', 'Akeeba Backup') &&
					(
						$data->attributes?->type === 'component' ||
						($data->attributes?->type === 'plugin' && $data->attributes?->folder === 'webservices')
					)
			),
			fn(bool $carry, object $data) => $carry && $data->attributes?->status == 1,
			true
		);

		if (!$allEnabled)
		{
			$warnings[] = 'akeebabackup';
		}

		// TODO Can I get a list of Akeeba Backup profiles?

		// TODO Check for Admin Tools component and its Web Services plugins

		// TODO Check if I can list WAF settings

		return $warnings;
	}

	/**
	 * Get the base URL of the site (instead of the API endpoint).
	 *
	 * @return  string
	 */
	public function getBaseUrl(): string
	{
		$url            = rtrim($this->url, "/ \t\n\r\0\x0B");

		if (str_ends_with($url, '/api'))
		{
			$url = rtrim(substr($url, 0, -4), '/');
		}

		return $url;
	}

	public function fixCoreUpdateSite(): void
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->container;
		$client = $container->httpFactory->makeClient(cache: false, singleton: false);

		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/core/update');

		$client->post($url, $options);
	}

	private function cleanUrl(?string $url): string
	{
		$url = trim($url ?? '');
		$uri = new Uri($url);

		if (!in_array($uri->getScheme(), ['http', 'https']))
		{
			$uri->setScheme('http');
		}

		$uri->setQuery('');
		$uri->setFragment('');
		$path = rtrim($uri->getPath(), '/');

		if (str_ends_with($path, '/api/index.php'))
		{
			$path = substr($path, 0, -10);
		}

		if (str_contains($path, '/api/'))
		{
			$path = substr($path, 0, strrpos($path, '/api/')) . '/api';
		}

		if (!str_ends_with($path, '/api'))
		{
			$path .= '/api';
		}

		$uri->setPath($path);

		return $uri->toString();
	}

}