<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\ApiRequestTrait;
use Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function call_user_func_array;
use function call_user_func_array as call_user_func_array1;

#[AsTask(
	name: 'refreshsiteinfo',
	description: 'PANOPTICON_TASKTYPE_REFRESHSITEINFO'
)]
class RefreshSiteInfo extends AbstractCallback
{
	use ApiRequestTrait;
	use JsonSanitizerTrait;
	use SaveSiteTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$limitStart = (int) $storage->get('limitStart', 0);
		$limit      = (int) $storage->get('limit', $params->get('limit', 10));
		$force      = (bool) $storage->get('force', $params->get('force', false));
		$filterIDs  = $storage->get('filter.ids', $params->get('ids', []));

		$siteIDs = $this->getSiteIDsForFetchInfo(
			limitStart: $limitStart,
			limit: $limit,
			force: $force,
			onlyTheseIDs: $filterIDs,
		);

		if (empty($siteIDs))
		{
			$this->logger->info('No sites in need to retrieve updated information for.');

			return Status::OK->value;
		}

		$this->logger->info(sprintf(
			'Found a further %d site(s) to retrieve updated information for.',
			count($siteIDs)
		));

		$this->fetchInfoForSiteIDs($siteIDs);

		$storage->set('limitStart', $limitStart + $limit);

		return Status::WILL_RESUME->value;
	}

	private function getSiteIDsForFetchInfo(int $limitStart = 0, int $limit = 10, bool $force = false, array $onlyTheseIDs = []): array
	{
		$db = $this->container->db;

		/**
		 * Reasoning behind this code:
		 *
		 * “The correct way to use LOCK TABLES and UNLOCK TABLES with transactional tables, such as InnoDB tables, is to
		 * begin a transaction with SET autocommit = 0 (not START TRANSACTION) followed by LOCK TABLES, and to not call
		 * UNLOCK TABLES until you commit the transaction explicitly.”
		 *
		 * This is meant to avoid deadlocks.
		 *
		 * @see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		 */
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__sites');

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__sites'))
			->where($db->quoteName('enabled') . ' = 1');

		if (!$force)
		{
			$frequency = (int) $this->container->appConfig->get('siteinfo_freq', 60);
			$frequency = min(1440, max(15, $frequency));

			$query->andWhere([
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.core.lastAttempt') . ') IS NULL',
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.core.lastAttempt') . ') < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL ' . $frequency . ' MINUTE))',
			], 'OR');
		}

		$onlyTheseIDs = ArrayHelper::toInteger($onlyTheseIDs, []);

		if (!empty($onlyTheseIDs))
		{
			$query->andWhere(
				$db->quoteName('id') . 'IN (' . implode(',', $onlyTheseIDs) . ')'
			);
		}

		$siteIDs = $db->setQuery($query, $limitStart, $limit)->loadColumn() ?: [];
		$siteIDs = array_filter(ArrayHelper::toInteger($siteIDs, []));

		/**
		 * Update config.core.lastAttempt to avoid the same sites being fetched by another process.
		 *
		 * We may have more than one site information updater process executing simultaneously, e.g. the periodic task
		 * and someone working on the CLI.
		 *
		 * Each process runs its own MySQL query which finds sites to fetch information for, based on each site's
		 * config.core.lastAttempt value and the current date and time.
		 *
		 * By updating this value while the #__sites table is locked, as soon as we get a list of site IDs, before we
		 * actually process the sites, we make sure that concurrent processes **cannot** select the sites we are going
		 * to be processing in **this** process.
		 *
		 * The reason we do this instead of using a queue is that enqueueing sites ultimately suffers from a similar
		 * problem. We can certainly lock the #__sites table to select all sites in need of an update and update the
		 * queue. However, this is contingent upon having enough execution time to execute this atomically. On resource
		 * constrained hosts with thousands of sites (and possibly a remote database) we might time out. This means we
		 * would need to perform enqueueing in smaller batches, e.g. 100 sites at a time, which brings us back to the
		 * issue of how do you enqueue sites without having more than one process try to enqueue the same site. This
		 * becomes really hard to solve without using e.g. Redis, so it's better to fudge it with this trick than
		 * implementing a semaphore system which would work on bottom-tier hosts. As always, there is method to my
		 * madness.
		 *
		 * As a final note: if you use the --force flag in CLI you are bypassing this protection. The --force flag
		 * really DOES FORCE things to happen, even if said things are utterly idiotic. You've been warned.
		 */
		if (!empty($siteIDs))
		{
			$updateQuery = $db->getQuery(true)
					->update($db->quoteName('#__sites'))
					->set(
						$db->quoteName('config') . ' = JSON_SET(' . $db->quoteName('config') . ', ' .
						$db->quote('$.core.lastAttempt') . ', UNIX_TIMESTAMP(NOW()))'
					)
					->where($db->quoteName('id')) . 'IN(' . implode(',', $siteIDs) . ')';
			$db->setQuery($updateQuery)->execute();
		}

		// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		$db->setQuery('COMMIT')->execute();
		$db->unlockTables();
		$db->setQuery('SET autocommit = 1')->execute();

		return $siteIDs;
	}

	private function fetchInfoForSiteIDs(array $siteIDs): void
	{
		$siteIDs = ArrayHelper::toInteger($siteIDs, []);

		if (empty($siteIDs))
		{
			return;
		}

		/**
		 * Set up an array of promises which will be resolved asynchronously.
		 *
		 * The idea is that each site is transformed to a Guzzle promise. Resolving the promise accesses the site's API
		 * and runs any post-processing required to store various information in the database, in the format Panopticon
		 * understands.
		 *
		 * Guzzle will execute the various requests concurrently, running the promise then() handlers asynchronously.
		 * This is substantialyl faster than executing requests serially. Essentially, we get to do productive work
		 * while the network stack is twiddling its thumbs waiting for remove servers' replies.
		 *
		 * @see https://docs.guzzlephp.org/en/stable/quickstart.html#async-requests
		 * @see https://docs.guzzlephp.org/en/stable/request-options.html
		 */
		$httpClient = $this->container->httpFactory->makeClient(cache: false);
		$promises = array_map(
			function (int $id) use ($httpClient) {
				$site = new Site($this->container);

				try
				{
					$site->findOrFail($id);
				}
				catch (\RuntimeException $e)
				{
					return null;
				}

				$this->logger->debug(sprintf(
					'Enqueueing site #%d (%s) for information update.',
					$site->id, $site->name
				));

				// Get the correct promise by CMS type
				$promise = match ($site->cmsType())
				{
					CMSType::JOOMLA => $this->promisePostProcJoomla(
						call_user_func_array(
							[$httpClient, 'getAsync'],
							$this->getRequestOptions($site, '/index.php/v1/panopticon/core/update?force=1')
						),
						$site
					),
					CMSType::WORDPRESS => $this->promisePostProcWordPress(
						call_user_func_array1(
							[$httpClient, 'getAsync'],
							$this->getRequestOptions($site, '/v1/panopticon/core/update?force=1')
						),
						$site
					),
					default => null
				};

				// Handle unknown CMS type (in this case $promise is NULL)
				if (!$promise instanceof PromiseInterface)
				{
					$this->logger->notice(
						sprintf(
							'Unknown CMS type \'%s\' for site #%d (%s)',
							$site->cmsType()->value ?? '(unknown)',
							$site->id,
							$site->name
						)
					);

					return null;
				}

				// Also get the favicon of the site
				$promise = $this->promisePostProcGetFavicon($promise, $site);

				return $promise;
			},
			$siteIDs
		);

		// Remove NULL items (invalid sites)
		$promises = array_filter($promises);

		// Do I still have any sites to process?
		if (empty($promises))
		{
			return;
		}

		// Wait until all promises are resolved
		Utils::settle($promises)->wait(true);
	}

	/**
	 * Update the promise to post-process a Joomla site's successful API result.
	 *
	 * @param   PromiseInterface  $promise  The Guzzle promise we'll be attaching to.
	 * @param   Site              $site     The site object instance.
	 *
	 * @return  PromiseInterface  The updated Guzzle promise instance
	 */
	private function promisePostProcJoomla(PromiseInterface $promise, Site $site): PromiseInterface
	{
		return $promise
			->then(
				function (ResponseInterface $response) use ($site) {
					try
					{
						$rawData = $this->sanitizeJson($response->getBody()->getContents());
						$document = @json_decode($rawData);
					}
					catch (\Exception $e)
					{
						$document = null;
					}

					$attributes = $document?->data?->attributes;

					if (empty($attributes))
					{
						$this->logger->notice(sprintf(
							'Could not retrieve information for Joomla! site #%d (%s). Invalid data returned from API call.',
							$site->id, $site->name
						));

						return $response;
					}

					$this->logger->debug(
						sprintf(
							'Retrieved information for Joomla! site #%d (%s).',
							$site->id,
							$site->name
						)
					);

					$this->saveSite(
						$site,
						function (Site $site) use ($rawData, $document, $attributes)
						{
							$config = $site?->getConfig() ?? new Registry();

							$config->set('core.current.version', $attributes->current);
							$config->set('core.current.stability', $attributes->currentStability);
							$config->set('core.latest.version', $attributes->latest ?? $attributes->current);
							$config->set('core.latest.stability', $attributes->latestStability ?? $attributes->currentStability);
							$config->set('core.php', $attributes->phpVersion ?? null);
							$config->set('core.extensionAvailable', $attributes->extensionAvailable ?? false);
							$config->set('core.updateSiteAvailable', $attributes->updateSiteAvailable ?? false);
							$config->set('core.maxCacheHours', $attributes->maxCacheHours ?? 6);
							$config->set('core.minimumStability', $attributes->minimumStability ?? 'stable');
							$config->set('core.lastUpdateTimestamp', $attributes->lastUpdateTimestamp ?? time());
							$config->set('core.lastAttempt', time());
							$config->set('core.overridesChanged', $attributes->overridesChanged ?? null);
							$config->set('core.serverInfo', $attributes->serverInfo ?? null);

							$stabilityCheck = match ($config->get('core.minimumStability', 'stable'))
							{
								default => in_array($config->get('core.latest.stability', 'stable'), ['stable']),
								'rc' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc',
								]),
								'beta' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta',
								]),
								'alpha' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta', 'alpha',
								]),
								'dev' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta', 'alpha', 'dev',
								]),
							};

							$config->set(
								'core.canUpgrade',
								version_compare(
									$config->get('core.current.version'),
									$config->get('core.latest.version'),
									'lt'
								)
								&& $stabilityCheck
								&& $config->get('core.extensionAvailable')
								&& $config->get('core.updateSiteAvailable')
							);

							$panopticon = $attributes->panopticon ?? null;

							if (!empty($panopticon) && (is_object($panopticon) || is_array($panopticon)))
							{
								foreach ($panopticon as $k => $v)
								{
									$config->set('core.panopticon.' . $k, $v);
								}
							}

							$admintools = $attributes->admintools ?? null;

							if (!empty($admintools) && (is_object($admintools) || is_array($admintools)))
							{
								foreach ($admintools as $k => $v)
								{
									$config->set('core.admintools.' . $k, $v);
								}
							}

							// Clear the last error message
							$config->set('core.lastErrorMessage', null);

							// Retrieve the SSL / TLS certificate information
							$config->set('ssl', $site->getCertificateInformation());

							// Retrieve the WHOIS information
							$config->set('whois', $site->getWhoIsInformation());

							// Latest backup information
							if ($site->hasAkeebaBackup())
							{
								$config->set('akeebabackup.latest', $this->getLatestBackup($site));
							}

							$site->config = $config;
						}
					);

					return $response;
				},
				function (Throwable $e) use ($site) {
					$this->logger->error(sprintf(
						'Could not retrieve information for Joomla! site #%d (%s). The server replied with the following error: %s',
						$site->id, $site->name, $e->getMessage()
					));

					// Save the last error message
					$this->saveSite(
						$site,
						function (Site $site) use ($e)
						{
							$config = $site?->getConfig() ?? new Registry();

							$config->set('core.lastErrorMessage', $e->getMessage());

							$site->config = $config;
						}
					);

					throw $e;
				}
			);
	}

	/**
	 * Update the promise to post-process a WordPress site's successful API result.
	 *
	 * @param   PromiseInterface  $promise  The Guzzle promise we'll be attaching to.
	 * @param   Site              $site     The site object instance.
	 *
	 * @return  PromiseInterface  The updated Guzzle promise instance
	 */
	private function promisePostProcWordPress(PromiseInterface $promise, Site $site): PromiseInterface
	{
		return $promise
			->then(
				function (ResponseInterface $response) use ($site) {
					try
					{
						$rawData = $this->sanitizeJson($response->getBody()->getContents());
						$document = @json_decode($rawData);
					}
					catch (\Exception $e)
					{
						$document = null;
					}

					if (empty($document))
					{
						$this->logger->notice(sprintf(
							'Could not retrieve information for WordPress site #%d (%s). Invalid data returned from API call.',
							$site->id, $site->name
						));

						return $response;
					}

					$this->logger->debug(
						sprintf(
							'Retrieved information for WordPress site #%d (%s).',
							$site->id,
							$site->name
						)
					);

					$this->saveSite(
						$site,
						function (Site $site) use ($rawData, $document)
						{
							$config = $site?->getConfig() ?? new Registry();

							// The following properties are not available under WordPress, therefore faked
							$config->set('core.extensionAvailable', true);
							$config->set('core.updateSiteAvailable', true);
							$config->set('core.maxCacheHours', 6);
							$config->set('core.overridesChanged', null);

							// Get properties from the API response
							$config->set('core.current.version', $document->current);
							$config->set('core.current.stability', $document->currentStability);
							$config->set('core.latest.version', $document->latest ?? $document->current);
							$config->set('core.latest.stability', $document->latestStability ?? $document->currentStability);
							$config->set('core.php', $document->phpVersion ?? null);
							$config->set('core.minimumStability', $document->minimumStability ?? 'stable');
							$config->set('core.lastUpdateTimestamp', $document->lastUpdateTimestamp ?? time());
							$config->set('core.lastAttempt', time());
							$config->set('core.serverInfo', $document->serverInfo ?? null);

							$stabilityCheck = match ($config->get('core.minimumStability', 'stable'))
							{
								default => in_array($config->get('core.latest.stability', 'stable'), ['stable']),
								'rc' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc',
								]),
								'beta' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta',
								]),
								'alpha' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta', 'alpha',
								]),
								'dev' => in_array($config->get('core.latest.stability', 'stable'), [
									'stable', 'rc', 'beta', 'alpha', 'dev',
								]),
							};

							$config->set(
								'core.canUpgrade',
								version_compare(
									$config->get('core.current.version'),
									$config->get('core.latest.version'),
									'lt'
								)
								&& $stabilityCheck
								&& $config->get('core.extensionAvailable')
								&& $config->get('core.updateSiteAvailable')
							);

							$panopticon = $document->panopticon ?? null;

							if (!empty($panopticon) && (is_object($panopticon) || is_array($panopticon)))
							{
								foreach ($panopticon as $k => $v)
								{
									$config->set('core.panopticon.' . $k, $v);
								}
							}

							$admintools = $document->admintools ?? null;

							if (!empty($admintools) && (is_object($admintools) || is_array($admintools)))
							{
								foreach ($admintools as $k => $v)
								{
									$config->set('core.admintools.' . $k, $v);
								}
							}

							// Clear the last error message
							$config->set('core.lastErrorMessage', null);

							// Retrieve the SSL / TLS certificate information
							$config->set('ssl', $site->getCertificateInformation());

							// Retrieve the WHOIS information
							$config->set('whois', $site->getWhoIsInformation());

							// Latest backup information
							if ($site->hasAkeebaBackup())
							{
								$config->set('akeebabackup.latest', $this->getLatestBackup($site));
							}

							$site->config = $config;
						}
					);

					return $response;
				},
				function (Throwable $e) use ($site) {
					$this->logger->error(sprintf(
						'Could not retrieve information for WordPress site #%d (%s). The server replied with the following error: %s',
						$site->id, $site->name, $e->getMessage()
					));

					// Save the last error message
					$this->saveSite(
						$site,
						function (Site $site) use ($e)
						{
							$config = $site?->getConfig() ?? new Registry();

							$config->set('core.lastErrorMessage', $e->getMessage());

							$site->config = $config;
						}
					);

					throw $e;
				}
			);
	}

	/**
	 * Update the promise to also retrieve the favicon of the site upon successful API access.
	 *
	 * @param   PromiseInterface  $promise  The Guzzle promise we'll be attaching to.
	 * @param   Site              $site     The site object instance.
	 *
	 * @return  PromiseInterface  The updated Guzzle promise instance
	 */
	private function promisePostProcGetFavicon(PromiseInterface $promise, Site $site): PromiseInterface
	{
		return $promise
			->then(
				function(ResponseInterface $response) use ($site) {
					$site->getFavicon(asDataUrl: true);

					return $response;
				}
			);
	}

	/**
	 * Get the latest backup record using the site's Akeeba Backup Professional JSON API.
	 *
	 * @param   Site  $site  The site object to retrieve backups from.
	 *
	 * @return  object|null The latest backup record as an object, or null if no backups are found.
	 * @since   1.1.0
	 */
	private function getLatestBackup(Site $site): ?object
	{
		try
		{
			$records = $site->akeebaBackupGetBackups(false, 0, 1, true);
		}
		catch (Throwable)
		{
			return null;
		}

		if (empty($records))
		{
			return null;
		}

		return $records[0];
	}
}