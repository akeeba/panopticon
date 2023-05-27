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
use Akeeba\Panopticon\Model\Task;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

#[AsTask(
	name: 'refreshinstalledextensions',
	description: 'PANOPTICON_TASKTYPE_REFRESHINSTALLEDEXTENSIONS'
)]
class RefreshInstalledExtensions extends AbstractCallback
{
	use ApiRequestTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		if ($task instanceof Task)
		{
			$params = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);
		}
		else
		{
			$params = new Registry();
		}

		$limitStart   = (int)$storage->get('limitStart', 0);
		$limit        = (int)$storage->get('limit', $params->get('limit', 10));
		$force        = (bool)$storage->get('force', $params->get('force', false));
		$forceUpdates = (bool)$storage->get('forceUpdates', $params->get('forceUpdates', false));
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

		$this->logger->info(sprintf(
			'Found a further %d site(s) to retrieve updated extensions information for.',
			count($siteIDs)
		));

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
			$frequency = (int)$this->container->appConfig->get('siteinfo_freq', 60);
			$frequency = min(1440, max(15, $frequency));

			$query->andWhere([
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.extensions.lastAttempt') . ') IS NULL',
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.extensions.lastAttempt') . ') < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL ' . $frequency . ' MINUTE))',
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
			function (int $id) use ($httpClient, $forceUpdates)
			{
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
					'Enqueueing site #%d (%s) for extensions list update.',
					$site->id, $site->name
				));

				$uriPath = '/index.php/v1/panopticon/extensions?page[limit]=10000';

				if ($forceUpdates)
				{
					$uriPath .= '&force=1';
				}

				[$url, $options] = $this->getRequestOptions($site, $uriPath);

				return $httpClient
					// See https://docs.guzzlephp.org/en/stable/quickstart.html#async-requests and https://docs.guzzlephp.org/en/stable/request-options.html
					->getAsync($url, $options)
					->then(
						function (ResponseInterface $response) use ($site)
						{
							try
							{
								$document = @json_decode($response->getBody()->getContents());
							}
							catch (\Exception $e)
							{
								$document = null;
							}

							$data = $document?->data;

							if (empty($data))
							{
								$this->logger->notice(sprintf(
									'Could not retrieve information for site #%d (%s). Invalid data returned from API call.',
									$site->id, $site->name
								));

								return;
							}

							$this->logger->debug(
								sprintf(
									'Retrieved information for site #%d (%s).',
									$site->id,
									$site->name
								)
							);

							$config = new Registry($site->getFieldValue('config') ?: '{}');

							$config->set(
								'extensions.list',
								$this->mapExtensionsList(
									array_filter(
										array_map(fn($item) => $item?->attributes, $data)
									)
								)
							);

							try
							{
								$site->save([
									'config' => $config->toString(),
								]);
							}
							catch (\Exception $e)
							{
								// Ah, shucks.
							}

							// Finally, clear the cache of known extensions for the specific site
							$cacheKey = 'site.' . $site->id;
							$this->logger->debug(
								sprintf(
									'Clearing cache of known extensions for site %d (pool ‘extensions’, item ‘%s’)',
									$site->id,
									$cacheKey
								)
							);
							$this->container->cacheFactory->pool('extensions')->delete($cacheKey);
						},
						function (RequestException $e) use ($site)
						{
							$this->logger->error(sprintf(
								'Could not retrieve extensions information for site #%d (%s). The server replied with the following error: %s',
								$site->id, $site->name, $e->getMessage()
							));
						}
					);
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

	private function mapExtensionsList(array $items): array
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
			fn($item) => (object)[
				'extension_id'   => $item?->extension_id,
				'name'           => $item?->name,
				'description'    => $item?->description,
				'type'           => $item?->type,
				'element'        => $item?->element,
				'folder'         => $item?->folder,
				'client_id'      => $item?->client_id,
				'type_s'         => $item?->type_translated,
				'folder_s'       => $item?->folder_translated,
				'client_s'       => $item?->client_translated,
				'enabled'        => $item?->enabled,
				'protected'      => $item?->protected,
				'locked'         => $item?->locked,
				'version'        => (object)[
					'current' => $item?->version,
					'new'     => $item?->new_version,
				],
				'author'         => $item?->author,
				'authorUrl'      => $item?->authorUrl,
				'authorEmail'    => $item?->authorEmail,
				'hasUpdateSites' => !empty($item?->updatesites),
				'downloadkey'    => (object)[
					'supported' => $item?->downloadkey?->supported ?? false,
					'valid'     => $item?->downloadkey?->valid ?? false,
					'value'     => $item?->downloadkey?->value ?? '',
				],
			],
			$items
		);
	}
}