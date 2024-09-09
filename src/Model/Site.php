<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\JoomlaUpdateRunState;
use Akeeba\Panopticon\Library\Enumerations\WordPressUpdateRunState;
use Akeeba\Panopticon\Library\SiteInfo\Retriever;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Trait\AdminToolsIntegrationTrait;
use Akeeba\Panopticon\Model\Trait\AkeebaBackupIntegrationTrait;
use Akeeba\Panopticon\Model\Trait\ApplyUserGroupsToSiteQueryTrait;
use Akeeba\Panopticon\Model\Trait\CmsFamilyFilterSeparatorTrait;
use Akeeba\Panopticon\Model\Trait\SiteTestConnectionJoomlaTrait;
use Akeeba\Panopticon\Model\Trait\SiteTestConnectionWPTrait;
use Akeeba\Panopticon\Task\RefreshSiteInfo;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Uri\Uri;
use Awf\User\User;
use Awf\Utils\ArrayHelper;
use Awf\Utils\Template;
use DateTime;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Exceptions\ServerMismatchException;
use Iodev\Whois\Exceptions\WhoisException;
use Iodev\Whois\Factory as WhoisFactory;
use Iodev\Whois\Loaders\CurlLoader;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Defines a site known to Panopticon
 *
 * @property int         $id           Task ID.
 * @property string      $name         The name of the site (user-visible).
 * @property string      $url          The URL to the site (with the /api part).
 * @property int         $enabled      Is this site enabled?
 * @property Date        $created_on   When was this site created?
 * @property int         $created_by   Who created this site?
 * @property null|Date   $modified_on  When was this site last modified?
 * @property null|int    $modified_by  Who last modified this site?
 * @property null|Date   $locked_on    When was this site last locked for writing?
 * @property null|int    $locked_by    Who last locked this site for writing?
 * @property Registry    $config       The configuration for this site.
 * @property null|string $notes        Freeform notes (in HTML).
 *
 * @since  1.0.0
 */
class Site extends DataModel
{
	use ApiRequestTrait;
	use AkeebaBackupIntegrationTrait;
	use AdminToolsIntegrationTrait;
	use ApplyUserGroupsToSiteQueryTrait;
	use JsonSanitizerTrait;
	use CmsFamilyFilterSeparatorTrait;
	use SiteTestConnectionJoomlaTrait;
	use SiteTestConnectionWPTrait;
	use SaveSiteTrait;

	/**
	 * Represents the configuration for a site as a Registry object.
	 *
	 * @var   null|Registry $configAsRegistry The configuration for a site as a Registry object, or null if not
	 *                                        available.
	 * @since 1.0.6
	 */
	private ?Registry $configAsRegistry = null;

	/**
	 * Group ID to group names map (used as cache).
	 *
	 * @var   null|string[]
	 * @since 1.0.6
	 */
	private ?array $groupMaps = null;

	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__sites';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->fieldsSkipChecks[] = 'enabled';

		$this->addBehaviour('filters');
	}

	public function reset($useDefaults = true, $resetRelations = false)
	{
		$this->configAsRegistry = null;
		$this->groupMaps        = null;

		return parent::reset($useDefaults, $resetRelations);
	}

	public function keyedList(bool $onlyEnabled = true): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('name'),
				]
			)
			->from($db->quoteName('#__sites'));

		if ($onlyEnabled)
		{
			$query->where(
				$db->quoteName('enabled') . ' = 1'
			);
		}

		try
		{
			return $db->setQuery($query)->loadAssocList('id', 'name') ?: [];
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$this->applyUserGroupsToQuery($query);

		$db = $this->container->db;

		// Filter: search
		$fltSearch = $this->getState('search', null, 'string');

		if (!empty(trim($fltSearch ?? '')))
		{
			$fltSearch = trim($fltSearch ?? '');

			$query->andWhere(
				[
					$db->quoteName('name') . ' LIKE ' . $db->quote('%' . $fltSearch . '%'),
					$db->quoteName('url') . ' LIKE ' . $db->quote('%' . $fltSearch . '%'),
				]
			);
		}

		// Filter: has potential core updates
		$fltCoreUpdates = $this->getState('coreUpdates', '', 'cmd');

		if ($fltCoreUpdates)
		{
			$query->where(
				[
					$query->jsonExtract($db->quoteName('config'), '$.core.canUpgrade') . ' = TRUE',
					$query->jsonExtract($db->quoteName('config'), '$.latest.version') . ' != ' .
					$query->jsonExtract($db->quoteName('config'), '$.current.version'),
				]
			);
		}
		elseif (!$fltCoreUpdates && $fltCoreUpdates !== '')
		{
			$query->andWhere(
				[
					$query->jsonExtract($db->quoteName('config'), '$.core.canUpgrade') . ' = FALSE',
					$query->jsonExtract($db->quoteName('config'), '$.core.latest.version') . ' = ' .
					$query->jsonExtract($db->quoteName('config'), '$.core.current.version'),
				]
			);
		}

		// Filter: has potential extension updates
		$fltExtUpdates = $this->getState('extUpdates', '', 'cmd');

		if ($fltExtUpdates == 1)
		{
			$query->where(
				[
					$query->jsonExtract($db->quoteName('config'), '$.extensions.hasUpdates') . ' = 1',
				]
			);
		}
		elseif ($fltExtUpdates == 0 && $fltExtUpdates !== '')
		{
			$query->where(
				[
					$query->jsonExtract($db->quoteName('config'), '$.extensions.hasUpdates') . ' = 0',
				]
			);
		}

		// Filters: cmsType and cmsFamily
		[$fltCmsType, $fltCmsFamily] = $this->separateCmsFamilyFilter($this->getState('cmsFamily', null, 'cmd'));
		$fltCmsType = $this->getState('cmsType', $fltCmsType, 'cmd');

		// Filter: cmsType
		if (is_string($fltCmsType))
		{
			// Reject invalid filter values
			$fltCmsType = strtolower(trim($fltCmsType));
			$fltCmsType = in_array($fltCmsType, array_column(CMSType::cases(), 'value'))
				? $fltCmsType : null;
		}

		if (!empty($fltCmsType))
		{
			// Special case for `joomla`. Legacy entries don't have a CMSType.
			if ($fltCmsType === CMSType::JOOMLA->value)
			{
				$query->where(
					'(' .
					$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote($fltCmsType) .
					' OR ' .
					$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' IS NULL' .
					')'
				);
			}
			else
			{
				$query->where(
					$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote($fltCmsType),
				);
			}
		}

		// Filter: cmsFamily
		if ($fltCmsFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.current.version') . ' LIKE ' .
				$query->quote('"' . $fltCmsFamily . '.%')
			);
		}

		// Filter: phpFamily
		$fltPHPFamily = $this->getState('phpFamily', null, 'cmd');

		if ($fltPHPFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.php') . ' LIKE ' . $query->quote(
					'"' . $fltPHPFamily . '.%'
				)
			);
		}

		// Filter: group
		$fltGroup = $this->getState('group', null) ?: [];

		if (!empty($fltGroup))
		{
			$fltGroup = is_string($fltGroup) && str_contains($fltGroup, ',') ? explode(',', $fltGroup) : $fltGroup;
			$fltGroup = is_array($fltGroup) ? $fltGroup : [trim($fltGroup)];
			$fltGroup = ArrayHelper::toInteger($fltGroup);
			$fltGroup = array_filter($fltGroup);
			$clauses  = [];

			foreach ($fltGroup as $gid)
			{
				$clauses[] = $query->jsonContains(
					$query->quoteName('config'), $query->quote('"' . (int) $gid . '"'), $query->quote('$.config.groups')
				);
				$clauses[] = $query->jsonContains(
					$query->quoteName('config'), $query->quote((int) $gid), $query->quote('$.config.groups')
				);
			}

			if (!empty($clauses))
			{
				$query->extendWhere('AND', $clauses, 'OR');
			}
		}

		return $query;
	}

	public function check()
	{
		$user        = $this->getContainer()->userManager->getUser();
		$uid         = $user->getId();
		$currentDate = $this->getContainer()->dateFactory()->toSql();

		$createdOn  = $this->getFieldValue('created_on', null);
		$createdBy  = $this->getFieldValue('created_by', null);
		$modifiedOn = $this->getFieldValue('modified_on', null);
		$modifiedBy = $this->getFieldValue('modified_by', null);

		if (empty($createdBy))
		{
			$createdOn  = $currentDate;
			$createdBy  = $uid;
			$modifiedBy = null;
			$modifiedOn = null;
		}
		else
		{
			$modifiedOn = $currentDate;
			$modifiedBy = $uid;
		}

		$this->setFieldValue('created_on', $createdOn);
		$this->setFieldValue('created_by', $createdBy);
		$this->setFieldValue('modified_on', $modifiedOn);
		$this->setFieldValue('modified_by', $modifiedBy);

		$this->setFieldValue('name', trim($this->getFieldValue('name', '') ?: ''));

		if (empty($this->getFieldValue('name', '')))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_ERR_NO_TITLE'));
		}

		if (empty($this->url))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_ERR_NO_URL'));
		}

		$cmsType = $this->getConfig()->get('cmsType', 'joomla');
		$this->getConfig()->set(
			'cmsType',
			in_array($cmsType, array_column(CMSType::cases(), 'value'))
				? $cmsType
				: 'joomla'
		);

		parent::check();

		$this->url = $this->cleanUrl($this->url);

		return $this;
	}

	public function testConnection(bool $getWarnings = true): array
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->testConnectionJoomla($getWarnings),
			CMSType::WORDPRESS => $this->testConnectionWordPress($getWarnings),
			default => []
		};
	}

	/**
	 * Get the base URL of the site (instead of the API endpoint).
	 *
	 * @return  string
	 */
	public function getBaseUrl(): string
	{
		$url = rtrim($this->url, "/ \t\n\r\0\x0B");

		switch ($this->cmsType())
		{
			case CMSType::WORDPRESS:
				if (str_ends_with(rtrim($url, '/'), '/wp-json'))
				{
					$url = substr(rtrim($url, '/'), 0, -8);
				}
				break;

			case CMSType::JOOMLA:
				if (str_ends_with($url, '/panopticon_api'))
				{
					$url = rtrim(substr($url, 0, -15), '?/');

					if (str_ends_with($url, '/index.php'))
					{
						$url = substr($url, 0, -10);
					}
				}
				elseif (str_ends_with($url, '/api'))
				{
					$url = rtrim(substr($url, 0, -4), '/');
				}
				break;
		}

		return $url;
	}

	/**
	 * Get the site administration interface login URL.
	 *
	 * If Admin Tools is installed this includes the secret word.
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getAdminUrl(bool $withCustomAdminDir = true): string
	{
		$cmsType = $this->cmsType();
		$url     = match ($cmsType)
		{
			CMSType::JOOMLA => $this->getBaseUrl() . '/administrator',
			CMSType::WORDPRESS => $this->getBaseUrl() . '/wp-admin'
		};

		$config = $this->getConfig();

		if (!$config->get('core.admintools.enabled', false) || $config->get('core.admintools.renamed', false))
		{
			return $url;
		}

		$adminDir = $config->get('core.admintools.admindir', 'administrator');
		$standardAdmin = match ($cmsType)
		{
			CMSType::JOOMLA => 'administrator',
			CMSType::WORDPRESS => 'wp-admin'
		};

		if (!$withCustomAdminDir)
		{
			$adminDir = $standardAdmin;
		}

		if (!empty($adminDir) && $adminDir !== $standardAdmin)
		{
			$url = $this->getBaseUrl() . '/' . trim($adminDir, '/');
		}

		$secretWord = trim($config->get('core.admintools.secret_word', '') ?: '');

		if (!empty($secretWord))
		{
			if (
				$cmsType === CMSType::JOOMLA &&
				(empty($adminDir) || $adminDir === 'administrator')
			)
			{
				$url .= '/index.php';
			}

			$url .= '?' . urlencode($secretWord);
		}

		return $url;
	}

	public function getAPIEndpointURL(): string
	{
		return rtrim($this->url, "/ \t\n\r\0\x0B");
	}

	public function fixCoreUpdateSite(): void
	{
		if ($this->cmsType() !== CMSType::JOOMLA)
		{
			throw new RuntimeException('This is only possible with Joomla! sites.');
		}

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
	public function getGroupsForSelect(?User $user = null, bool $includeEmpty = false): array
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
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('title'),
				]
			)
			->from($db->quoteName('#__groups'));

		if (!empty($groupFilter))
		{
			$query->where($db->quoteName('id') . ' IN(' . implode(',', array_map([$db, 'quote'], $groupFilter)) . ')');
		}

		$ret = array_map(fn($x) => $x->title, $db->setQuery($query)->loadObjectList('id') ?: []);

		if ($includeEmpty)
		{
			$ret[''] = $this->getLanguage()->text('PANOPTICON_SITES_LBL_GROUPS_PLACEHOLDER');
		}

		return $ret;
	}

	public function getExtensionsUpdateTask(): ?Task
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->getSiteSpecificTask('extensionsupdate'),
			CMSType::WORDPRESS => $this->getSiteSpecificTask('pluginsupdate'),
			default => null
		};
	}

	public function getPluginsUpdateTask(): ?Task
	{
		return $this->getExtensionsUpdateTask();
	}

	public function getJoomlaUpdateTask(): ?Task
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->getSiteSpecificTask('joomlaupdate'),
			CMSType::WORDPRESS => $this->getSiteSpecificTask('wordpressupdate'),
			default => null
		};
	}

	public function getWordPressUpdateTask(): ?Task
	{
		return $this->getJoomlaUpdateTask();
	}

	public function isExtensionsUpdateTaskStuck(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskStuck('extensionsupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskStuck('pluginsupdate'),
			default => false
		};
	}

	public function isPluginsUpdateTaskStuck(): bool
	{
		return $this->isExtensionsUpdateTaskStuck();
	}

	public function isJoomlaUpdateTaskStuck(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskStuck('joomlaupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskStuck('wordpressupdate'),
			default => false
		};
	}

	public function isWordPressUpdateTaskStuck(): bool
	{
		return $this->isJoomlaUpdateTaskStuck();
	}

	public function isExtensionsUpdateTaskScheduled(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskScheduled('extensionsupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskScheduled('pluginsupdate'),
			default => false
		};
	}

	public function isPluginsUpdateTaskScheduled(): bool
	{
		return $this->isExtensionsUpdateTaskScheduled();
	}

	public function isJoomlaUpdateTaskScheduled(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskScheduled('joomlaupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskScheduled('wordpressupdate'),
			default => false
		};
	}

	public function isWordPressUpdateTaskScheduled(): bool
	{
		return $this->isJoomlaUpdateTaskScheduled();
	}

	public function isJoomlaUpdateTaskRunning(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskRunning('joomlaupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskRunning('wordpressupdate'),
			default => false
		};
	}

	public function isWordPressUpdateTaskRunning(): bool
	{
		return $this->isJoomlaUpdateTaskRunning();
	}

	public function isExtensionsUpdateTaskRunning(): bool
	{
		return match ($this->cmsType()) {
			CMSType::JOOMLA => $this->isSiteSpecificTaskRunning('extensionsupdate'),
			CMSType::WORDPRESS => $this->isSiteSpecificTaskRunning('pluginsupdate'),
			default => false
		};
	}

	public function isPluginsUpdateTaskRunning(): bool
	{
		return $this->isExtensionsUpdateTaskRunning();
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

	/**
	 * Get summary information about extensions
	 *
	 * @param   array|null  $extensions  An array of extension objects. NULL to use the result of getExtensionsList().
	 *
	 * @return  object Quick information about the extensions:
	 *                - update: the number of extensions with available updates
	 *                - key: the number of extensions with missing download IDs
	 *                - site: the number of extensions without update sites
	 * @since   1.0.6
	 */
	public function getExtensionsQuickInfo(?array $extensions = null): object
	{
		$extensions ??= $this->getExtensionsList();

		$ret = (object) [
			'update' => 0,
			'key'    => 0,
			'site'   => 0,
		];

		/** @var \Akeeba\Panopticon\Model\Sysconfig $sysConfigModel */
		$sysConfigModel = $this->getModel('Sysconfig');

		foreach ($extensions as $item)
		{
			$extensionkey = $sysConfigModel->getExtensionShortname(
				$item->type, $item->element, $item->folder, $item->client_id
			);

			if (empty($extensionkey) || $sysConfigModel->isExcludedShortname($extensionkey))
			{
				continue;
			}

			$currentVersion    = $item->version?->current;
			$latestVersion     = $item->version?->new;
			$noUpdateSite      = !($item->hasUpdateSites ?? false);
			$missingDownloadID = ($item->downloadkey?->supported ?? false)
			                     && !($item->downloadkey?->valid ?? false);
			$hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
			                     && ($currentVersion != $latestVersion)
			                     && version_compare($currentVersion, $latestVersion, 'lt');

			if ($noUpdateSite)
			{
				$ret->site++;
			}

			if ($missingDownloadID)
			{
				$ret->key++;
			}

			if ($hasUpdate)
			{
				$ret->update++;
			}
		}

		return $ret;
	}

	public function getExtensionsScheduledForUpdate(): array
	{
		$queueName    = sprintf('extensions.%d', $this->getId());
		$altQueueName = sprintf('plugins.%d', $this->getId());
		$db           = $this->getDbo();
		$query        = $db->getQuery(true);
		$query
			->select($query->jsonExtract($db->quoteName('item'), '$.data'))
			->from($db->quoteName('#__queue'))
			->where(
				[
					$query->jsonExtract($db->quoteName('item'), '$.siteId') . ' = ' . (int) $this->getId(),
				]
			)->extendWhere(
				'AND', [
				$query->jsonExtract($db->quoteName('item'), '$.queueType') . ' = ' . $db->quote($queueName),
				$query->jsonExtract($db->quoteName('item'), '$.queueType') . ' = ' . $db->quote($altQueueName),
			], 'OR'
			);


		try
		{
			return array_map(
				function ($json) {
					$item = json_decode($json);

					return $item->id ?? null;
				},
				$db->setQuery($query)->loadColumn() ?: []
			);
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	public function getConfig(): Registry
	{
		if ($this->configAsRegistry === null)
		{
			$config                 = $this->getFieldValue('config');
			$this->configAsRegistry = ($config instanceof Registry) ? $config : (new Registry($config));
		}

		return $this->configAsRegistry;
	}

	public function saveDownloadKey(int $extensionId, ?string $key): void
	{
		if ($this->cmsType() !== CMSType::JOOMLA)
		{
			throw new RuntimeException('This is only possible with Joomla! sites.');
		}

		$extensions = (array) $this->getConfig()->get('extensions.list');

		if (!array_key_exists($extensionId, $extensions))
		{
			throw new RuntimeException(
				sprintf('Extension #%d does not exist in site #%d (%s)', $extensionId, $this->getId(), $this->name)
			);
		}

		$extension = $extensions[$extensionId];
		$dlKeyInfo = $extension?->downloadkey;

		if ($dlKeyInfo?->supported !== true || !is_array($dlKeyInfo?->updatesites) || empty($dlKeyInfo?->updatesites))
		{
			throw new RuntimeException(
				sprintf(
					'Extension #%d (%s) in site #%d (%s) does not support Download Keys', $extensionId,
					$extension->description, $this->getId(), $this->name
				)
			);
		}

		// For each update site, save the Download Key
		$updateSites = ArrayHelper::toInteger($dlKeyInfo->updatesites);
		/** @var \GuzzleHttp\Client $httpClient */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		$promises   = array_map(
			function (int $id) use ($httpClient, $dlKeyInfo, $key) {
				$uriPath = sprintf('/index.php/v1/panopticon/updatesite/%s', $id);
				[$url, $options] = $this->getRequestOptions($this, $uriPath);

				/**
				 * ⚠️ WARNING! ⚠️
				 * 1. Even though the field is called extra_query we must send the raw key. The server adds the prefix
				 *    and suffix.
				 * 2. We MUST use RequestOptions::JSON instead of RequestOptions::FORM_PARAMS because the data must be
				 *    sent as a JSON document (Content-Type: application/json and the body encoded as JSON). This is
				 *    exactly what RequestOptions::JSON does.
				 * 3. The server returns the raw key, without the prefix and suffix, after saving the Download Key.
				 */
				$options[RequestOptions::JSON] = [
					'extra_query' => $key,
				];

				return $httpClient
					->patchAsync($url, $options)
					->then(
						function (ResponseInterface $response) use ($key) {
							try
							{
								$document = @json_decode($this->sanitizeJson($response->getBody()->getContents()));
							}
							catch (\Exception $e)
							{
								$document = null;
							}

							$errors = $document?->errors ?? [];

							if (is_array($errors) && !empty($errors))
							{
								$message = array_shift($errors);

								throw new RuntimeException($message);
							}

							$data = $document?->data;

							if (empty($data) || empty($data?->attributes))
							{
								throw new RuntimeException(
									sprintf(
										'Could not retrieve information for site #%d (%s). Invalid data returned from API call.',
										$this->getId(), $this->name
									)
								);
							}

							if ($data?->attributes?->extra_query != $key)
							{
								throw new RuntimeException("Could not save the Download Key");
							}

							return true;
						},
						function (RequestException $e) {
							throw new RuntimeException(
								sprintf(
									'Could not save the Download Key for site #%d (%s). The server replied with the following error: %s',
									$this->id, $this->name, $e->getMessage()
								)
							);
						}
					)
					->otherwise(
						function (Throwable $e) {
							return $e;
						}
					);
			}, $updateSites
		);

		$responses = Utils::settle($promises)->wait(true);

		foreach ($responses as $response)
		{
			if (!isset($response['value']))
			{
				continue;
			}

			if ($response['value'] instanceof Throwable)
			{
				throw $response['value'];
			}
		}

		// Reload the update information
		/** @var RefreshSiteInfo $callback */
		$callback = $this->container->taskRegistry->get('refreshinstalledextensions');
		$dummy    = new \stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', 1);
		$registry->set('force', true);
		$registry->set('forceUpdates', true);
		$registry->set('filter.ids', [$this->getId()]);

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);
	}

	/**
	 * Get the CMS type for this site
	 *
	 * @return  CMSType The CMS type
	 * @since   1.0.6
	 */
	public function cmsType(): CMSType
	{
		$cmsType = $this->getConfig()->get('cmsType', 'joomla') ?: 'joomla';

		return CMSType::from($cmsType);
	}

	/**
	 * Get the current run state of the Joomla update task
	 *
	 * @return  JoomlaUpdateRunState  The run state of the Joomla update task
	 * @since   1.0.6
	 */
	public function getJoomlaUpdateRunState(): JoomlaUpdateRunState
	{
		// This is NOT a Joomla! site.
		if ($this->cmsType() !== CMSType::JOOMLA)
		{
			return JoomlaUpdateRunState::NOT_A_JOOMLA_SITE;
		}

		$config           = $this->getConfig();
		$joomlaUpdateTask = $this->getJoomlaUpdateTask();

		// Special statuses for Joomla! core files refresh
		if (
			$config->get('core.lastAutoUpdateVersion') === $config->get('core.current.version')
			&& !is_null($joomlaUpdateTask)
			&& $joomlaUpdateTask->last_exit_code != Status::OK->value
		)
		{
			if ($joomlaUpdateTask->enabled && $joomlaUpdateTask->last_exit_code == Status::INITIAL_SCHEDULE->value)
			{
				return JoomlaUpdateRunState::REFRESH_SCHEDULED;
			}

			if ($joomlaUpdateTask->enabled
			    && in_array(
				    $joomlaUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]
			    ))
			{
				return JoomlaUpdateRunState::REFRESH_RUNNING;
			}

			if ($joomlaUpdateTask->last_exit_code === Status::EXCEPTION->value || !$joomlaUpdateTask->enabled)
			{
				return JoomlaUpdateRunState::REFRESH_ERROR;
			}

			return JoomlaUpdateRunState::INVALID_STATE;
		}

		// We're told there is no update available.
		if (!$config->get('core.canUpgrade', false))
		{
			return JoomlaUpdateRunState::CANNOT_UPGRADE;
		}

		// There is no scheduled update task.
		if ($joomlaUpdateTask === null)
		{
			return JoomlaUpdateRunState::NOT_SCHEDULED;
		}

		// There is an update task, but it's disabled
		if (!$joomlaUpdateTask->enabled && in_array($joomlaUpdateTask->last_exit_code, [Status::INITIAL_SCHEDULE->value, Status::OK->value]))
		{
			return JoomlaUpdateRunState::NOT_SCHEDULED;
		}

		// A new version is available, the update task is enabled, but has returned correctly. Should never happen!
		if ($joomlaUpdateTask->enabled && $joomlaUpdateTask->last_exit_code == Status::OK->value)
		{
			return JoomlaUpdateRunState::NOT_SCHEDULED;
		}

		// There is a scheduled update task which will run later.
		if ($joomlaUpdateTask->enabled && $joomlaUpdateTask->last_exit_code == Status::INITIAL_SCHEDULE->value)
		{
			return JoomlaUpdateRunState::SCHEDULED;
		}

		// The update task is scheduled, and running.
		if (($joomlaUpdateTask->enabled
		     && in_array(
			     $joomlaUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]
		     )))
		{
			return JoomlaUpdateRunState::RUNNING;
		}

		// An error occurred
		if ($joomlaUpdateTask->last_exit_code != Status::OK->value)
		{
			return JoomlaUpdateRunState::ERROR;
		}

		// We should never be here.
		return JoomlaUpdateRunState::INVALID_STATE;
	}

	/**
	 * Get the current run state of the WordPress update task
	 *
	 * @return  WordPressUpdateRunState  The run state of the WordPress update task
	 * @since   1.0.6
	 */
	public function getWordPressUpdateRunState(): WordPressUpdateRunState
	{
		// This is NOT a Joomla! site.
		if ($this->cmsType() !== CMSType::WORDPRESS)
		{
			return WordPressUpdateRunState::NOT_A_WORDPRESS_SITE;
		}

		$config           = $this->getConfig();
		$wpUpdateTask = $this->getWordPressUpdateTask();

		// Special statuses for WP core files refresh (these are not currently supported, therefore mapped to errors)
		if (
			$config->get('core.lastAutoUpdateVersion') === $config->get('core.current.version')
			&& !is_null($wpUpdateTask)
			&& $wpUpdateTask->last_exit_code != Status::OK->value
		)
		{
			if ($wpUpdateTask->enabled && $wpUpdateTask->last_exit_code == Status::INITIAL_SCHEDULE->value)
			{
				// This would indicate a scheduled Refresh, but we don't support it for WordPress
				return WordPressUpdateRunState::INVALID_STATE;
			}

			if ($wpUpdateTask->enabled
			    && in_array(
				    $wpUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]
			    ))
			{
				// This would indicate a running Refresh, but it's not supported for WordPress
				return WordPressUpdateRunState::INVALID_STATE;
			}

			if ($wpUpdateTask->last_exit_code === Status::EXCEPTION->value || !$wpUpdateTask->enabled)
			{
				// This would indicate a refresh error, but it's not supported for WordPress
				return WordPressUpdateRunState::ERROR;
			}

			return WordPressUpdateRunState::INVALID_STATE;
		}

		// We're told there is no update available.
		if (!$config->get('core.canUpgrade', false))
		{
			return WordPressUpdateRunState::CANNOT_UPGRADE;
		}

		// There is no scheduled update task.
		if ($wpUpdateTask === null)
		{
			return WordPressUpdateRunState::NOT_SCHEDULED;
		}

		// There is an update task, but it's disabled
		if (!$wpUpdateTask->enabled && in_array($wpUpdateTask->last_exit_code, [Status::INITIAL_SCHEDULE->value, Status::OK->value]))
		{
			return WordPressUpdateRunState::NOT_SCHEDULED;
		}

		// A new version is available, the update task is enabled, but has returned correctly. Should never happen!
		if ($wpUpdateTask->enabled && $wpUpdateTask->last_exit_code == Status::OK->value)
		{
			return WordPressUpdateRunState::NOT_SCHEDULED;
		}

		// There is a scheduled update task which will run later.
		if ($wpUpdateTask->enabled && $wpUpdateTask->last_exit_code == Status::INITIAL_SCHEDULE->value)
		{
			return WordPressUpdateRunState::SCHEDULED;
		}

		// The update task is scheduled, and running.
		if (($wpUpdateTask->enabled
		     && in_array(
			     $wpUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]
		     )))
		{
			return WordPressUpdateRunState::RUNNING;
		}

		// An error occurred
		if ($wpUpdateTask->last_exit_code != Status::OK->value)
		{
			return WordPressUpdateRunState::ERROR;
		}

		// We should never be here.
		return WordPressUpdateRunState::INVALID_STATE;
	}

	/**
	 * Can we possibly schedule a core files refresh on a Joomla! site?
	 *
	 * @return  bool
	 * @since   1.0.6
	 */
	public function canRefreshCoreJoomlaFiles(): bool
	{
		if ($this->cmsType() !== CMSType::JOOMLA)
		{
			return false;
		}

		$config = $this->getConfig();

		return !$config->get('core.canUpgrade', false)
		       && $config->get('core.extensionAvailable', true)
		       && $config->get('core.updateSiteAvailable', true);
	}

	/**
	 * Is the user allowed to edit this site?
	 *
	 * @param   User|null  $user  The user to check if they're authorised to edit. NULL for current user.
	 *
	 * @return  bool
	 * @since   1.0.6
	 */
	public function canEdit(?User $user = null): bool
	{
		/** @var \Akeeba\Panopticon\Library\User\User $user */
		$user ??= $this->container->userManager->getUser();

		return $user->authorise('panopticon.admin', $this)
		       || $user->authorise('panopticon.editown', $this);
	}

	/**
	 * Get the groups for this site
	 *
	 * @param   bool  $asString  Optional. Whether to return the groups as strings instead of integers. Default is
	 *                           false.
	 *
	 * @return  array|string  The groups as an array of integers (IDs) or strings (names), depending on the value of
	 *                        $asString. If $asString is false, the return value is an array of group IDs (integers).
	 *                        If $asString is true, the return value is an array of group names (strings).
	 * @since   1.0.6
	 */
	public function getGroups(bool $asString = false): array
	{
		$groups = $this->getConfig()->get('config.groups', []) ?: [];

		if (empty($groups) || !is_array($groups))
		{
			return [];
		}

		$groups = array_unique($groups);

		if (!$asString)
		{
			return $groups;
		}

		$this->groupMaps ??= $this->getContainer()->mvcFactory->makeTempModel('groups')->getGroupMap();

		$groups = array_map(
			fn($x) => $this->groupMaps[$x] ?? null,
			$groups
		);
		$groups = array_filter($groups);

		sort($groups);

		return $groups;
	}

	/**
	 * Retrieves the favicon URL
	 *
	 * @param   int          $minSize    Minimum size of the favicon (default: 0)
	 * @param   string|null  $type       The type of the favicon (default: null)
	 * @param   bool         $asDataUrl  Return a `data:` URL instead of an HTTP URL.
	 *
	 * @return  string|null  The URL of the favicon, or null if it could not be found.
	 *
	 * @throws CacheException
	 * @throws InvalidArgumentException
	 * @since   1.1.0
	 */
	public function getFavicon(
		int $minSize = 0, ?string $type = null, bool $asDataUrl = false, bool $onlyIfCached = false
	): ?string
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container          = $this->getContainer();
		$pool               = $container->cacheFactory->pool('favicon');
		$callbackController = new CallbackController(
			$container,
			$pool
		);
		$cacheKey           = hash(
			'sha1',
			sprintf(
				'favicon-%d-%s-%d-%s-%s', $this->getId(), $this->getBaseUrl(), $minSize, $type ?? '',
				$asDataUrl ? 'data' : 'url'
			)
		);

		if ($onlyIfCached && !$pool->hasItem($cacheKey))
		{
			return null ?? $this->getDefaultFavicon();
		}

		return $callbackController
			->get(
				fn($minSize, $type) => (new Retriever($this->getContainer(), $this->getBaseUrl(), true))
					->getIconUrl($minSize, $type, true, $asDataUrl),
				[$minSize, $type],
				$cacheKey,
				31536000
			) ?? $this->getDefaultFavicon();
	}

	private function getDefaultFavicon(): ?string
	{
		$filePath = Template::parsePath('media://images/globe-solid.svg', true, $this->getContainer()->application);

		if (!file_exists($filePath))
		{
			return null;
		}

		$contents = @file_get_contents($filePath);

		if ($contents === false)
        {
            return null;
        }

		return sprintf("data:%s;base64,%s", 'image/svg+xml', base64_encode($contents));
	}

	/**
	 * Returns select WHOIS information for the domain.
	 *
	 * @param   int  $timeout  The network timeout, in seconds
	 *
	 * @return  object|null
	 */
	#[\JetBrains\PhpStorm\ObjectShape([
		'created'     => 'string|null',
		'expiration'  => 'string|null',
		'registrar'   => 'string|null',
		'nameservers' => 'string[]|null',
	])]
	public function getWhoIsInformation(int $timeout = 3): ?object
	{
		/** @var \Akeeba\Panopticon\Container $container */
		$container          = $this->getContainer();
		$pool               = $container->cacheFactory->pool('whois');
		$callbackController = new CallbackController(
			$container,
			$pool
		);
		$cacheKey           = hash(
			'sha1',
			"whois-{$this->getId()}-{$this->getBaseUrl()}"
		);

		return $callbackController
			       ->get(
				       function ($timeout) {
					       // Get the hostname and port from the URL
					       try
					       {
						       // This gets the domain.tld for the site; very important when we're given a subdomain.
						       $hostname           = Uri::getInstance($this->getBaseUrl())->getHost();
						       $parts              = explode('.', $hostname);
						       $parts              = array_slice($parts, -2);
						       $applicableHostname = implode('.', $parts);
						       $info               = WhoisFactory::get()
							       ->createWhois(new CurlLoader($timeout))
							       ->loadDomainInfo($applicableHostname);
					       }
					       catch (Exception)
					       {
						       return null;
					       }

					       if (empty($info))
					       {
						       return null;
					       }

					       return (object) [
						       'domain'      => $info->domainName,
						       'created'     => $info->creationDate,
						       'expiration'  => $info->expirationDate,
						       'registrar'   => $info->registrar,
						       'nameservers' => $info->nameServers,
					       ];
				       },
				       [$timeout],
				       $cacheKey,
				       86400
			       );
	}

	public function getDomainValidityStatus(): int
	{
		$created = $this->getConfig()->get('whois.created', null);
		$expires = $this->getConfig()->get('whois.expiration', null);

		try
		{
			$created = empty($created) ? null : new DateTime($created);
		}
		catch (\Throwable)
		{
			$created = null;
		}

		try
		{
			$expires = empty($expires) ? null : new DateTime($expires);
		}
		catch (\Throwable)
		{
			$expires = null;
		}

		// No valid Created or Expires date: -1 (unknown state)
		if (empty($created) && empty($expires))
		{
			return -1;
		}

		$warning  = $this->getConfig()->get('config.domain.warning', 180);
		$now      = new DateTime();
		$warnDate = $warning > 0
			? (new DateTime())->add(new \DateInterval(sprintf('P%sD', $warning)))
			: $now;

		// Both Created and Expires defined, both within range: 0 (valid)
		if (!empty($created) && !empty($expires) && $created <= $now && $expires >= $now && $expires > $warnDate)
		{
			return 0;
		}

		// Only Created defined and is valid: 0 (valid)
		if (!empty($created) && empty($expires) && $created <= $now)
		{
			return 0;
		}

		// Only Expires defined and is valid: 0 (valid)
		if (empty($created) && !empty($expires) && $expires >= $now && $expires > $warnDate)
		{
			return 0;
		}

		// Created is defined but invalid: 1 (too soon)
		if (!empty($created) && $created > $now)
		{
			return 1;
		}

		// Too close to the expiration date: 2 (expiration warning)
		if ($warning > 0 && !empty($expires) && $expires >= $now && $expires <= $warnDate)
		{
			return 2;
		}

		// Expires is defined but invalid: 3 (expired)
		if (!empty($expires) && $expires < $now)
		{
			return 3;
		}

		// No idea WTF is going on: -1
		return -1;
	}

	/**
	 * Returns select TLS certificate information which are relevant to our use case.
	 *
	 * @param   string  $url      The URL of the site to fetch the SSL certificate from.
	 * @param   int     $timeout  The timeout, in seconds, for the fetch operation.
	 *
	 * @return  null|object
	 * @since   1.1.0
	 */
	#[\JetBrains\PhpStorm\ObjectShape([
		'commonName'         => 'string|null',
		'hash'               => 'string|null',
		'type'               => 'string|null',
		'issuerCommonName'   => 'string|null',
		'issuerOrganisation' => 'string|null',
		'validFrom'          => 'DateTime|null',
		'validTo'            => 'DateTime|null',
	])]
	public function getCertificateInformation(int $timeout = 3): ?object
	{
		// Make sure the PHP OpenSSL extension is installed.
		if (!function_exists('openssl_x509_parse'))
		{
			return null;
		}

		// Get the hostname and port from the URL
		$uri      = new Uri($this->getBaseUrl());
		$hostname = $uri->getHost();
		$scheme   = $uri->getScheme() ?? 'https';
		$port     = (int) ($uri->getPort() ?: ($scheme === 'https' ? 443 : 80));

		// If the hostname is empty, the scheme is not HTTPS, or the port is out-of-range we have nothing to do.
		if (empty($hostname) || $scheme !== 'https' || $port <= 0 || $port >= 65536)
		{
			return null;
		}

		// Open the raw SSL socket stream to fetch the remote server's certificate.

		try
		{
			$socketResource = stream_socket_client(
				sprintf('ssl://%s:%d', $hostname, $port),
				$errorNumber,
				$errorString,
				$timeout,
				STREAM_CLIENT_CONNECT,
				stream_context_create(
					[
						'ssl' => [
							'verify_peer'             => false,
							'verify_peer_name'        => false,
							'allow_self_signed'       => true,
							'security_level'          => 0,
							'capture_peer_cert'       => true,
							'capture_peer_cert_chain' => true,
						],
					]
				)
			);

			if (!is_resource($socketResource))
			{
				return null;
			}
		}
		catch (Throwable)
		{
			return null;
		}

		// Get the certificate and close the stream
		$cert = stream_context_get_params($socketResource);

		fclose($socketResource);

		// Make sure we have indeed captured the peer certificate
		if (
			!is_array($cert)
			|| !isset($cert['options'])
			|| !is_array($cert['options'])
			|| !isset($cert['options']['ssl'])
			|| !is_array($cert['options']['ssl'])
			|| !isset($cert['options']['ssl']['peer_certificate'])
		)
		{
			return null;
		}

		// Parse the raw binary certificate using the PHP OpenSSL extension.
		try
		{
			$certificateInformation = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
		}
		catch (Throwable)
		{
			$certificateInformation = false;
		}

		if ($certificateInformation === false)
		{
			return null;
		}

		// Check if the certificate has a valid signature, given its chain.
		$signatureVerified = true;

		if ($caChain = $cert['options']['ssl']['peer_certificate_chain'])
		{
			// Verify all certificates form a valid chain
			for ($i = 1; $i < count($caChain); $i++)
			{
				$signatureVerified = $signatureVerified && openssl_x509_verify($caChain[$i - 1], $caChain[$i]) == 1;
			}

			/**
			 * If I have more than two certificates we have intermediate certificates. Check the second to last and/or
			 * second certificate can be verified outside the chain.
			 */
			if ($signatureVerified && count($caChain) > 2)
			{
				$secondToLastCert  = $caChain[count($caChain) - 2];
				$secondCert        = $caChain[1];
				$signatureVerified = openssl_x509_checkpurpose($secondToLastCert, X509_PURPOSE_ANY, [AKEEBA_CACERT_PEM])
				                     || openssl_x509_checkpurpose($secondCert, X509_PURPOSE_ANY, [AKEEBA_CACERT_PEM]);
			}
		}

		// Decode the validity from / to time
		try
		{
			$validFrom = new DateTime('@' . $certificateInformation['validFrom_time_t'] ?? '0');
		}
		catch (Throwable)
		{
			$validFrom = null;
		}

		try
		{
			$validTo = new DateTime('@' . $certificateInformation['validTo_time_t'] ?? '0');
		}
		catch (Throwable)
		{
			$validTo = null;
		}

		$commonNames = null;

		if ($altName = $certificateInformation['extensions']['subjectAltName'] ?? null)
		{
			$commonNames = array_map(
				fn($x) => trim(substr($x, 4)),
				array_filter(
					array_map(
						'trim',
						explode(',', $altName)
					),
					fn($x) => str_starts_with($x, 'DNS:')
				)
			);
		}
		elseif ($cn = $certificateInformation['subject']['CN'] ?? null)
		{
			$commonNames = [$cn];
		}

		// Return the information which is relevant to us
		return (object) [
			'commonName'         => $commonNames,
			'hash'               => $certificateInformation['hash'] ?? null,
			'serialHex'          => $certificateInformation['serialNumberHex'],
			'type'               => $certificateInformation['signatureTypeLN'] ?? null,
			'issuerCommonName'   => $certificateInformation['issuer']['CN'] ?? null,
			'issuerOrganisation' => $certificateInformation['issuer']['O'] ?? null,
			'validFrom'          => $validFrom->format('Y-m-d H:i:s'),
			'validTo'            => $validTo->format('Y-m-d H:i:s'),
			'verified'           => $signatureVerified,
		];
	}

	/**
	 * Get the validFrom or validTo date for the site's SSL certificate.
	 *
	 * @param   bool  $from  True to return validFrom, false to return validTo.
	 *
	 * @return  DateTime|null  ValidFrom date if available, null if not available or invalid
	 * @since   1.1.0
	 */
	public function getSSLValidityDate(bool $from): ?DateTime
	{
		$raw = $this->getConfig()->get('ssl.valid' . ($from ? 'From' : 'To'));

		if (empty($raw))
		{
			return null;
		}

		try
		{
			return new DateTime($raw);
		}
		catch (Exception)
		{
			return null;
		}
	}

	/**
	 * Get the SSL validity status of the website
	 *
	 * @return  int  The SSL validity status:
	 *  -1: Unknown state
	 *  0: Valid
	 *  1: Too soon
	 *  2: Expiration warning
	 *  3: Expired
	 *
	 * @since  1.1.0
	 */
	public function getSSLValidityStatus(): int
	{
		$from = $this->getSSLValidityDate(true);
		$to   = $this->getSSLValidityDate(false);

		// No From or To: -1 (unknown state)
		if (empty($from) && empty($to))
		{
			return -1;
		}

		$warning  = $this->getConfig()->get('config.ssl.warning', 7);
		$now      = new DateTime();
		$warnDate = $warning > 0
			? (new DateTime())->add(new \DateInterval(sprintf('P%sD', $warning)))
			: $now;

		// Both From and To defined, both within range: 0 (valid)
		if (!empty($from) && !empty($to) && $from <= $now && $to >= $now && $to > $warnDate)
		{
			return 0;
		}

		// Only From defined and is valid: 0 (valid)
		if (!empty($from) && empty($to) && $from <= $now)
		{
			return 0;
		}

		// Only To defined and is valid: 0 (valid)
		if (empty($from) && !empty($to) && $to >= $now && $to > $warnDate)
		{
			return 0;
		}

		// From is defined but invalid: 1 (too soon)
		if (!empty($from) && $from > $now)
		{
			return 1;
		}

		// Too close to the expiration date: 2 (expiration warning)
		if ($warning > 0 && !empty($to) && $to >= $now && $to <= $warnDate)
		{
			return 2;
		}

		// To is defined but invalid: 3 (expired)
		if (!empty($to) && $to < $now)
		{
			return 3;
		}

		// No idea WTF is going on: -1
		return -1;
	}

	public function getSSLValidDomain(): bool
	{
		$sslDomains = $this->getConfig()->get('ssl.commonName');

		// Look, I have no idea, or you don't have an SSL cert. I won't report an error in this case. Okay?
		if (!is_array($sslDomains) || empty($sslDomains))
		{
			return true;
		}

		$currentDomain = (new Uri($this->getBaseUrl()))->getHost();

		// I don't know what my own domain is! 🙈 I will not report an error.
		if (empty($currentDomain))
		{
			return true;
		}

		foreach ($sslDomains as $domain)
		{
			if ($domain === $currentDomain || fnmatch($domain, $currentDomain))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Set the config attribute of the object.
	 *
	 * This method is called when using either the magic accessors, or the setFieldValue() method.
	 *
	 * It will automatically flag the config as changed if it no longer matches the getConfig() method's object.
	 *
	 * @param   string|Registry|null  $config  The config attribute to be set. Can be a string, Registry object, or
	 *                                         null.
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	protected function setConfigAttribute(string|Registry|null $config): void
	{
		if (is_string($config) || $config === null)
		{
			$this->recordData['config'] = $config;

			if ($config !== $this->getConfig()->toString())
			{
				$this->configAsRegistry = null;
			}

			return;
		}

		$this->recordData['config'] = $config->toString();

		if ($this->recordData['config'] !== $this->getConfig()->toString())
		{
			$this->configAsRegistry = null;
		}
	}

	protected function getSiteSpecificTask(string $type): ?Task
	{
		$taskModel = $this->getContainer()->mvcFactory->makeTempModel('Task');
		/** @var DataModel\Collection $taskCollection */
		$taskCollection = $taskModel
			->where('site_id', '=', $this->getId())
			->where('type', '=', $type)
			->get(0, 100);

		if ($taskCollection->isEmpty())
		{
			return null;
		}

		/** @var Task $task */
		$task          = $taskCollection->first();
		$task->storage = $task->storage instanceof Registry ? $task->storage : new Registry($task->storage ?: '{}');

		return $task;
	}

	protected function getSiteSpecificTasks(string $type): DataModel\Collection
	{
		$taskModel = $this->getContainer()->mvcFactory->makeTempModel('Task');
		/** @var DataModel\Collection $taskCollection */
		$taskCollection = $taskModel
			->where('site_id', '=', $this->getId())
			->where('type', '=', $type)
			->get(0, 100);

		return $taskCollection;
	}

	protected function isSiteSpecificTaskStuck(string $type): bool
	{
		$task = $this->getSiteSpecificTask($type);

		if (empty($task))
		{
			return false;
		}

		return !in_array(
			$task->last_exit_code, [
				Status::INITIAL_SCHEDULE->value,
				Status::OK->value,
				Status::RUNNING->value,
				Status::WILL_RESUME->value,
			]
		);
	}

	protected function isSiteSpecificTaskScheduled(string $type): bool
	{
		$task = $this->getSiteSpecificTask($type);

		if (empty($task) || !$task->enabled || empty($task->next_execution))
		{
			return false;
		}

		if (!$task->next_execution instanceof Date)
		{
			try
			{
				$task->next_execution = $this->container->dateFactory($task->next_execution);
			}
			catch (Exception $e)
			{
				return false;
			}
		}

		if ($task->last_exit_code !== Status::INITIAL_SCHEDULE->value
		    && $task->next_execution < ($this->container->dateFactory()))
		{
			return false;
		}

		return in_array(
			$task->last_exit_code, [
				Status::INITIAL_SCHEDULE->value,
				Status::RUNNING->value,
				Status::WILL_RESUME->value,
			]
		);
	}

	protected function isSiteSpecificTaskRunning(string $type): bool
	{
		$task = $this->getSiteSpecificTask($type);

		if (empty($task) || !$task->enabled || empty($task->next_execution))
		{
			return false;
		}

		if (!$task->next_execution instanceof Date)
		{
			try
			{
				$task->next_execution = $this->container->dateFactory($task->next_execution);
			}
			catch (Exception $e)
			{
				return false;
			}
		}

		return in_array(
			$task->last_exit_code, [
				Status::RUNNING->value,
				Status::WILL_RESUME->value,
			]
		);
	}

	protected function onBeforeDelete($id)
	{
		if (empty($id))
		{
			return;
		}

		// Remove all tasks attached to this site
		/** @var Tasks $taskModel */
		$taskModel = $this->getContainer()->mvcFactory->makeTempModel('Tasks');
		$taskModel->setState('site_id', $id);
		$taskModel->get(true)->delete();
	}

	private function cleanUrl(?string $url): string
	{
		$url = trim($url ?? '');

		if (str_ends_with($url, '?/panopticon_api'))
		{
			$url = substr($url, 0, -16) . '/index.php/panopticon_api';

			if (str_ends_with($url, '/index.php/index.php/panopticon_api'))
			{
				$url = substr($url, 0, -35) . '/index.php/panopticon_api';
			}
		}

		$uri = new Uri($url);

		if (!in_array($uri->getScheme(), ['http', 'https']))
		{
			$uri->setScheme('http');
		}

		$uri->setQuery('');
		$uri->setFragment('');
		$path = rtrim($uri->getPath(), '/');

		switch ($this->cmsType())
		{
			case CMSType::JOOMLA:
				if (!str_ends_with($path, '/panopticon_api'))
				{
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
				}
				break;

			case CMSType::WORDPRESS:
				$path = rtrim($path, '/');

				if (!str_ends_with($path, '/wp-json'))
				{
					$path .= '/wp-json';
				}

				/**
				 * Explanation: A user accidentally selects Joomla!, pastes the API info, realises their mistake,
				 * switches the type to WordPress but forgets to paste the API URL again. The Joomla! API code converted
				 * <URL>/wp-json to <URL>/wp-json/api, and the if-block right above this comment converted *that* to
				 * <URL>/wp-json/api/wp-json. We catch that, removing the /api/wp-json part.
				 */
				if (str_ends_with($path, '/wp-json/api/wp-json'))
				{
					$path = substr($path, 0, -12);
				}
				break;
		}

		$uri->setPath($path);

		return $uri->toString();
	}

	private function updateDebugInfoInSession(
		?ResponseInterface $response = null, ?string $responseBody = null, ?Throwable $e = null,
		string $prefix = 'testconnection.'
	): void
	{
		$session = $this->getContainer()->segment;

		$session->set($prefix . 'http_status', null);
		$session->set($prefix . 'body', null);
		$session->set($prefix . 'headers', null);
		$session->set($prefix . 'exception.type', null);
		$session->set($prefix . 'exception.message', null);
		$session->set($prefix . 'exception.file', null);
		$session->set($prefix . 'exception.line', null);
		$session->set($prefix . 'exception.trace', null);

		if ($e instanceof Throwable)
		{
			$session->set($prefix . 'exception.type', get_class($e));
			$session->set($prefix . 'exception.message', $e->getMessage());
			$session->set($prefix . 'exception.file', $e->getFile());
			$session->set($prefix . 'exception.line', $e->getLine());
			$session->set($prefix . 'exception.trace', $e->getTraceAsString());
		}

		if ($response instanceof ResponseInterface)
		{
			try
			{
				$session->set($prefix . 'http_status', $response->getStatusCode());
			}
			catch (Throwable $e)
			{
				$session->set($prefix . 'http_status', null);
			}

			try
			{
				$session->set($prefix . 'body', $responseBody);
			}
			catch (Throwable $e)
			{
				$session->set($prefix . 'body', null);
			}

			try
			{
				$session->set($prefix . 'headers', $response->getHeaders());
			}
			catch (Throwable $e)
			{
				$session->set('headers', null);
			}
		}
	}
}