<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
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

#[AsTask(
	name: 'refreshinstalledextensions',
	description: 'PANOPTICON_TASKTYPE_REFRESHINSTALLEDEXTENSIONS'
)]
class RefreshInstalledExtensions extends AbstractCallback
{
	use ApiRequestTrait;
	use JsonSanitizerTrait;
	use SaveSiteTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$params = $task?->params ?? null;
		$params = ($params instanceof Registry) ? $params : new Registry($params);

		$limitStart   = (int) $storage->get('limitStart', 0);
		$limit        = (int) $storage->get('limit', $params->get('limit', 10));
		$force        = (bool) $storage->get('force', $params->get('force', false));
		$forceUpdates = (bool) $storage->get('forceUpdates', $params->get('forceUpdates', false));
		$filterIDs    = $storage->get('filter.ids', $params->get('ids', []));

		$siteIDs = $this->getSiteIDsForExtensionsRefresh(
			limitStart: $limitStart,
			limit: $limit,
			force: $force,
			onlyTheseIDs: $filterIDs,
		);

		if (empty($siteIDs))
		{
			$this->logger->info('No sites in need to retrieve updated extensions information for.');

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'Found a further %d site(s) to retrieve updated extensions information for.',
				count($siteIDs)
			)
		);

		$this->fetchExtensionsForSiteIDs($siteIDs, $forceUpdates);

		$storage->set('limitStart', $limitStart + $limit);

		return Status::WILL_RESUME->value;
	}

	private function getSiteIDsForExtensionsRefresh(
		int $limitStart = 0, int $limit = 10, bool $force = false, array $onlyTheseIDs = []
	): array
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

			$query->andWhere(
				[
					'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.extensions.lastAttempt')
					. ') IS NULL',
					'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.extensions.lastAttempt')
					. ') < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL ' . $frequency . ' MINUTE))',
				], 'OR'
			);
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
		 * Update config.extensions.lastAttempt to avoid the same sites being fetched by another process.
		 *
		 * We may have more than one site information updater process executing simultaneously, e.g. the periodic task
		 * and someone working on the CLI.
		 *
		 * Each process runs its own MySQL query which finds sites to fetch information for, based on each site's
		 * config.extensions.lastAttempt value and the current date and time.
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
					               $db->quote('$.extensions.lastAttempt') . ', UNIX_TIMESTAMP(NOW()))'
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

	private function fetchExtensionsForSiteIDs(array $siteIDs, bool $forceUpdates = false): void
	{
		$siteIDs = ArrayHelper::toInteger($siteIDs, []);

		if (empty($siteIDs))
		{
			return;
		}

		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$promises = array_map(
			function (int $id) use ($httpClient, $forceUpdates) {
				$site = new Site($this->container);

				try
				{
					$site->findOrFail($id);
				}
				catch (\RuntimeException $e)
				{
					return null;
				}

				$this->logger->debug(
					sprintf(
						'Enqueueing site #%d (%s) for extensions list update.',
						$site->id, $site->name
					)
				);

				switch ($site->cmsType())
				{
					case CMSType::JOOMLA:
						$uriPath = '/index.php/v1/panopticon/extensions?page[limit]=10000';

						if ($forceUpdates)
						{
							$uriPath .= '&force=1';
						}

						[$url, $options] = $this->getRequestOptions($site, $uriPath);

						$promise = $httpClient->getAsync($url, $options);
						$promise = $this->promisePostProcJoomla($promise, $site);

						return $promise;

					case CMSType::WORDPRESS:
						[$url, $options] = $this->getRequestOptions($site, '/v1/panopticon/extensions');

						$promise = $httpClient->getAsync($url, $options);
						$promise = $this->promisePostProcWordPress($promise, $site);

						return $promise;

					default:
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
			},
			$siteIDs
		);

		$promises = array_filter($promises);

		if (empty($promises))
		{
			return;
		}

		Utils::settle($promises)->wait(true);

		// After storing all extensions we need to bust the cache of all known extensions
		$this->logger->debug('Clearing cache of all known extensions (pool ‘extensions’, item ‘all’)');
		$this->container->cacheFactory->pool('extensions')->delete('all');
	}

	private function promisePostProcJoomla(PromiseInterface $promise, Site $site): PromiseInterface
	{
		return $promise
			->then(
				function (ResponseInterface $response) use ($site) {
					try
					{
						$rawData  = $this->sanitizeJson($response->getBody()->getContents());
						$document = @json_decode($rawData);
					}
					catch (\Exception $e)
					{
						$document = null;
					}

					$data = $document?->data;

					$this->saveSite(
						$site,
						function (Site $site) use ($data, $rawData) {
							$config = $site->getConfig();
							$config->set('extensions.lastErrorMessage', null);

							if (empty($data))
							{
								$this->logger->notice(
									sprintf(
										'Could not retrieve information for Joomla! site #%d (%s). Invalid data returned from API call.',
										$site->id, $site->name
									)
								);

								$config->set(
									'extensions.lastErrorMessage',
									sprintf(
										"Invalid (non-JSON) data returned from the Joomla! API. Probably a third party plugin is breaking the API application? Raw data as follows:\n\n%s",
										$rawData ?: '(no data)'
									)
								);
							}
							else
							{
								$this->logger->debug(
									sprintf(
										'Retrieved information for Joomla! site #%d (%s).',
										$site->id,
										$site->name
									)
								);

								$extensions = $this->mapJoomlaExtensionsList(
									array_filter(
										array_map(fn($item) => $item?->attributes, $data)
									)
								);
								$config->set(
									'extensions.list',
									$extensions
								);

								// Save a flag for the existence of updates
								$hasUpdates = array_reduce(
									$extensions,
									function (bool $carry, object $item): int {
										$current = $item?->version?->current;
										$new     = $item?->version?->new;

										if ($carry || empty($current) || empty($new))
										{
											return $carry;
										}

										return version_compare($current, $new, 'lt');
									},
									false
								);

								$config->set('extensions.hasUpdates', $hasUpdates);
							}

							$site->config = $config->toString();
						}
					);

					// Finally, clear the cache of known extensions for the specific site
					$cacheKey = 'site.' . $site->id;
					$this->logger->debug(
						sprintf(
							'Clearing cache of known extensions for Joomla! site %d (pool ‘extensions’, item ‘%s’)',
							$site->id,
							$cacheKey
						)
					);
					$this->container->cacheFactory->pool('extensions')->delete($cacheKey);
				},
				function (\Throwable $e) use ($site) {
					$this->saveSite(
						$site,
						function (Site $site) use ($e) {
							$this->logger->error(
								sprintf(
									'Could not retrieve extensions information for Joomla! site #%d (%s). The server replied with the following error: %s',
									$site->id, $site->name, $e->getMessage()
								)
							);

							$config = $site->getConfig();
							$config->set('extensions.lastErrorMessage', $e->getMessage());
							$site->config = $config;
						}
					);
				}
			);
	}

	private function promisePostProcWordPress(PromiseInterface $promise, Site $site): PromiseInterface
	{
		return $promise
			->then(
				function (ResponseInterface $response) use ($site) {
					try
					{
						$rawData  = $this->sanitizeJson($response->getBody()->getContents());
						$document = @json_decode($rawData);
					}
					catch (\Exception $e)
					{
						$document = null;
					}

					$this->saveSite(
						$site,
						function (Site $site) use ($document, $rawData) {
							$config = $site->getConfig();
							$config->set('extensions.lastErrorMessage', null);

							if (
								empty($document)
								|| !is_object($document)
								|| ($document->plugins ?? null) === null
								|| !is_array($document->plugins)
								|| ($document->themes ?? null) === null
								|| !is_array($document->themes)
							)
							{
								$this->logger->notice(
									sprintf(
										'Could not retrieve information for WordPress site #%d (%s). Invalid data returned from API call.',
										$site->id, $site->name
									)
								);

								$config->set(
									'extensions.lastErrorMessage',
									sprintf(
										"Invalid (non-JSON) data returned from the WordPress API. Probably a third party plugin is breaking the WordPress JSON API application? Raw data as follows:\n\n%s",
										$rawData ?: '(no data)'
									)
								);
							}
							else
							{
								$this->logger->debug(
									sprintf(
										'Retrieved information for WordPress site #%d (%s).',
										$site->id,
										$site->name
									)
								);

								$extensions = array_merge(
									$this->mapWordPressPluginsList(array_filter($document->plugins ?: [])),
									$this->mapWordPressThemesList(array_filter($document->themes ?: []))
								);

								$config->set('extensions.list', (object) $extensions);

								// Save a flag for the existence of updates
								$hasUpdates = array_reduce(
									$extensions,
									function (bool $carry, object $item): int {
										$current = $item?->version?->current;
										$new     = $item?->version?->new;

										if ($carry || empty($current) || empty($new))
										{
											return $carry;
										}

										return version_compare($current, $new, 'lt');
									},
									false
								);

								$config->set('extensions.hasUpdates', $hasUpdates);
							}

							$site->config = $config->toString();
						}
					);

					// Finally, clear the cache of known extensions for the specific site
					$cacheKey = 'site.' . $site->id;
					$this->logger->debug(
						sprintf(
							'Clearing cache of known extensions for WordPress site %d (pool ‘extensions’, item ‘%s’)',
							$site->id,
							$cacheKey
						)
					);
					$this->container->cacheFactory->pool('extensions')->delete($cacheKey);
				},
				function (\Throwable $e) use ($site) {
					$this->saveSite(
						$site,
						function (Site $site) use ($e) {
							$this->logger->error(
								sprintf(
									'Could not retrieve extensions information for WordPress site #%d (%s). The server replied with the following error: %s',
									$site->id, $site->name, $e->getMessage()
								)
							);

							$config = $site->getConfig();
							$config->set('extensions.lastErrorMessage', $e->getMessage());
							$site->config = $config;
						}
					);
				}
			);
	}

	private function mapJoomlaExtensionsList(array $items): array
	{
		if (empty($items))
		{
			return [];
		}

		$items = array_combine(
			array_map(
				fn($item) => $item->extension_id,
				$items
			),
			$items
		);

		return array_map(
			fn($item) => (object) [
				'extension_id'   => $item?->extension_id ?? null,
				'name'           => $item?->name ?? null,
				'description'    => $item?->description ?? null,
				'type'           => $item?->type ?? null,
				'element'        => $item?->element ?? null,
				'folder'         => $item?->folder ?? null,
				'client_id'      => $item?->client_id ?? null,
				'type_s'         => $item?->type_translated ?? null,
				'folder_s'       => $item?->folder_translated ?? null,
				'client_s'       => $item?->client_translated ?? null,
				'enabled'        => $item?->enabled ?? null,
				'protected'      => $item?->protected ?? null,
				'locked'         => $item?->locked ?? null,
				'version'        => (object) [
					'current' => $item?->version ?? null,
					'new'     => $item?->new_version ?? null,
				],
				'author'         => $item?->author ?? null,
				'authorUrl'      => $item?->authorUrl ?? null,
				'authorEmail'    => $item?->authorEmail ?? null,
				'hasUpdateSites' => !empty($item?->updatesites ?? null),
				'naughtyUpdates' => $item?->naughtyUpdates ?? null,
				'downloadkey'    => (object) [
					'supported'   => $item?->downloadkey?->supported ?? false,
					'valid'       => $item?->downloadkey?->valid ?? false,
					'value'       => $item?->downloadkey?->value ?? '',
					'prefix'      => $item?->downloadkey?->prefix ?? '',
					'suffix'      => $item?->downloadkey?->suffix ?? '',
					'updatesites' => empty($item?->updatesites)
						? []
						: array_filter(
							array_map(
								fn($updatesite) => $updatesite?->update_site_id ?? null,
								(array) ($item?->updatesites ?: [])
							)
						),
				],
			],
			$items
		);
	}

	private function mapWordPressPluginsList(array $items): array
	{
		if (empty($items))
		{
			return [];
		}

		return array_map(
			function ($item): object {
				if (str_contains($item?->id ?? '', '/'))
				{
					[$folder, $element] = explode('/', $item?->id ?? '/');
				}
				else
				{
					$folder = $item?->id ?? '';
					$element = null;
				}

				return (object) [
					'extension_id'   => $item?->id ?? null,
					'name'           => $item?->name ?? null,
					'description'    => $item?->description ?? null,
					'type'           => 'plugin',
					'element'        => $element,
					'folder'         => $folder,
					'client_id'      => 1,
					'type_s'         => 'Plugin',
					'folder_s'       => $folder,
					'client_s'       => 'WordPress',
					'enabled'        => $item?->enabled ?? null,
					'protected'      => false,
					'locked'         => false,
					'version'        => (object) [
						'current' => $item?->version ?? null,
						'new'     => $item?->update?->new_version ?? null,
					],
					'author'         => $item?->author ?? null,
					'authorUrl'      => $item?->author_uri ?? null,
					'authorEmail'    => null,
					'hasUpdateSites' => true,
					'naughtyUpdates' => false,
					'update'         => is_object($item?->update) ? (object) [
						'infourl'     => $item->update->url ?? null,
						'downloadurl' => $item->update->package ?? null,
						'version'     => $item->update->new_version ?? null,
					] : null,
				];
			},
			$items
		);
	}

	private function mapWordPressThemesList(array $items): array
	{
		if (empty($items))
		{
			return [];
		}

		return array_map(
			function ($item): object {
				return (object) [
					'extension_id'   => $item?->id ?? null,
					'name'           => $item?->name ?? null,
					'description'    => $item?->description ?? null,
					'type'           => 'template',
					'element'        => $item?->id ?? null,
					'folder'         => null,
					'client_id'      => 1,
					'type_s'         => 'Theme',
					'folder_s'       => null,
					'client_s'       => 'WordPress',
					'enabled'        => true,
					'protected'      => false,
					'locked'         => false,
					'version'        => (object) [
						'current' => $item?->version ?? null,
						'new'     => $item?->update?->new_version ?? null,
					],
					'author'         => $item?->author ?? null,
					'authorUrl'      => $item?->author_uri ?? null,
					'authorEmail'    => null,
					'hasUpdateSites' => true,
					'naughtyUpdates' => false,
					'update'         => is_object($item?->update) ? (object) [
						'infourl'     => $item->update->url ?? null,
						'downloadurl' => $item->update->package ?? null,
						'version'     => $item->update->new_version ?? null,
					] : null,
				];
			},
			$items
		);
	}

}