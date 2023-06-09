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
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Task\ApiRequestTrait;
use Awf\Container\Container;
use Awf\Database\Query;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\User\User;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use stdClass;
use Throwable;

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

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$this->applyUserGroupsToQuery($query);

		return $query;
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

			$options                                  = $container->httpFactory->getDefaultRequestOptions();
			$options[RequestOptions::HEADERS]         = [
				'Accept'     => 'application/vnd.api+json',
				'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
			];
			$options[RequestOptions::HTTP_ERRORS]     = false;
			$options[RequestOptions::CONNECT_TIMEOUT] = $connectTimeout;
			$options[RequestOptions::TIMEOUT]         = $totalTimeout;

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
		catch (Throwable $e)
		{
			$results = new stdClass();
		}

		if (empty($results?->data))
		{
			throw new WebServicesInstallerNotEnabled('Cannot list installed extensions. Web Services - Installer is not enabled.');
		}

		// Check if Panopticon is enabled
		$allEnabled = array_reduce(
			array_filter(
				$results->data,
				fn(object $data) => str_contains($data->attributes?->name ?? '', 'Panopticon')
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
				fn(object $data) => str_contains($data->attributes?->name ?? '', 'Akeeba Backup') &&
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
		$url = rtrim($this->url, "/ \t\n\r\0\x0B");

		if (str_ends_with($url, '/api'))
		{
			$url = rtrim(substr($url, 0, -4), '/');
		}

		return $url;
	}

	public function getAPIEndpointURL(): string
	{
		$url = rtrim($this->url, "/ \t\n\r\0\x0B");

		return $this->url;
	}

	public function fixCoreUpdateSite(): void
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->container;
		$client    = $container->httpFactory->makeClient(cache: false, singleton: false);

		[$url, $options] = $this->getRequestOptions($this, '/index.php/v1/panopticon/core/update');

		$client->post($url, $options);
	}

	/**
	 * Get the user groups which can be applied by the given user to this site
	 *
	 * @param   User|null  $user  The user. NULL for currently logged in user.
	 *
	 * @return  array Keyed array of id=>title, i.e. [id=>title, ...]
	 */
	public function getGroupsForSelect(?User $user = null): array
	{
		$user        ??= $this->container->userManager->getUser();
		$groupFilter = [];

		// If it's not a Super User I need to filter which user groups I am going to present.
		if (!$user->getPrivilege('panopticon.super'))
		{
			$groupFilter = array_keys($user->getGroupPrivileges());

			if (empty($groupFilter))
			{
				return [];
			}
		}

		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
			])
			->from($db->quoteName('#__groups'));

		if (!empty($groupFilter))
		{
			$query->where($db->quoteName('id') . ' IN(' . implode(',', array_map([$db, 'quote'], $groupFilter)) . ')');
		}

		return array_map(fn($x) => $x->title, $db->setQuery($query)->loadObjectList('id') ?: []);
	}

	public function getExtensionsUpdateTask(): ?Task
	{
		return $this->getSiteSpecificTask('extensionsupdate');
	}

	public function getJoomlaUpdateTask(): ?Task
	{
		return $this->getSiteSpecificTask('joomlaupdate');
	}

	public function isExtensionsUpdateTaskStuck(): bool
	{
		return $this->isSiteSpecificTaskStuck('extensionsupdate');
	}

	public function isJoomlaUpdateTaskStuck(): bool
	{
		return $this->isSiteSpecificTaskStuck('joomlaupdate');
	}

	public function isExtensionsUpdateTaskScheduled(): bool
	{
		return $this->isSiteSpecificTaskScheduled('extensionsupdate');
	}

	public function isJoomlaUpdateTaskScheduled(): bool
	{
		return $this->isSiteSpecificTaskScheduled('joomlaupdate');
	}

	public function getExtensionsList(bool $sortByName = true): array
	{
		$config     = $this->getConfig();
		$extensions = (array) $config->get('extensions.list', []);
		$extensions = $extensions ?: [];

		if ($sortByName)
		{
			uasort($extensions, fn($a, $b) => $a->name <=> $b->name);
		}

		return $extensions;
	}

	public function getConfig(): Registry
	{
		$config = $this->getFieldValue('config');

		return ($config instanceof Registry) ? $config : (new Registry($config));
	}

	protected function getSiteSpecificTask(string $type): ?Task
	{
		static $tasks = [];

		if (!array_key_exists($type, $tasks))
		{
			$tasks[$type] = null;

			$taskModel = DataModel::getTmpInstance(modelName: 'Task', container: $this->container);
			/** @var DataModel\Collection $taskCollection */
			$taskCollection = $taskModel
				->site_id($this->id)
				->type('extensionsupdate')
				->get(0, 100);

			if ($taskCollection->isEmpty())
			{
				return null;
			}

			/** @var Task $task */
			$task          = $taskCollection->first();
			$task->storage = $task->storage instanceof Registry ? $task->storage : new Registry($task->storage ?: '{}');

			$tasks[$type] = $task;
		}

		return $tasks[$type];
	}

	protected function isSiteSpecificTaskStuck(string $type): bool
	{
		$task = $this->getSiteSpecificTask($type);

		if (empty($task))
		{
			return false;
		}

		return !in_array($task->last_exit_code, [
			Status::INITIAL_SCHEDULE->value, Status::OK->value,
			Status::RUNNING->value, Status::WILL_RESUME->value,
		]);
	}

	protected function isSiteSpecificTaskScheduled(string $type): bool
	{
		$task = $this->getExtensionsUpdateTask($type);

		if (empty($task) || !$task->enabled || empty($task->next_execution))
		{
			return false;
		}

		if (!$task->next_execution instanceof Date)
		{
			try
			{
				$task->next_execution = new Date($task->next_execution);
			}
			catch (Exception $e)
			{
				return false;
			}
		}

		if ($task->next_execution < (new Date()))
		{
			return false;
		}

		return in_array($task->last_exit_code, [
			Status::INITIAL_SCHEDULE->value,
			Status::RUNNING->value, Status::WILL_RESUME->value,
		]);
	}

	private function applyUserGroupsToQuery(Query $query): void
	{
		// Get the user, so we can apply per group privilege checks
		$user = $this->container->userManager->getUser();

		// If the user is a Super User, or has a global view privilege, we have no checks to make
		if ($user->getPrivilege('panopticon.view'))
		{
			return;
		}

		// In any other case, get the list of groups for the user and limit listing sites visible to these groups
		$groupPrivileges = $user->getGroupPrivileges();

		if (empty($groupPrivileges))
		{
			// There are no groups the user belongs to. Therefore, the user can only see their own sites.
			$query->where($query->quoteName('created_by') . ' = ' . $query->quote($user->getId()));

			return;
		}

		// Filter out groups with read privileges
		$groupPrivileges = array_filter(
			$groupPrivileges,
			fn($privileges) => in_array('panopticon.view', $privileges)
		);

		if (empty($groupPrivileges))
		{
			// There are no groups with read privileges the user belongs to. Therefore, the user can only see their own sites.
			$query->where($query->quoteName('created_by') . ' = ' . $query->quote($user->getId()));

			return;
		}

		// We allow the user to view their own sites
		$clauses = [
			$query->quoteName('created_by') . ' = ' . $query->quote($user->getId()),
		];

		// Basically: a bunch of JSON_CONTAINS(`config`, '1', '$.config.groups') with ORs between them
		foreach (array_keys($groupPrivileges) as $gid)
		{
			$clauses[] = $query->jsonContains($query->quoteName('config'), $query->quote('"' . (int) $gid . '"'), $query->quote('$.config.groups'));
			$clauses[] = $query->jsonContains($query->quoteName('config'), $query->quote((int) $gid), $query->quote('$.config.groups'));
		}

		$query->extendWhere('AND', $clauses, 'OR');
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