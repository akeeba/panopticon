<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
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
	name: 'refreshsiteinfo',
	description: 'PANOPTICON_TASKTYPE_REFRESHSITEINFO'
)]
class RefreshSiteInfo extends AbstractCallback implements LoggerAwareInterface
{
	use LoggerAwareTrait;
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

		$db->lockTable('#__sites');

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__sites'))
			->where($db->quoteName('enabled') . ' = 1');

		if (!$force)
		{
			$query->andWhere([
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.core.lastAttempt') . ') IS NULL',
				'JSON_EXTRACT(' . $db->quoteName('config') . ', ' . $db->quote('$.core.lastAttempt') . ') < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 HOUR))',
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

		$db->unlockTables();

		return $siteIDs;
	}

	private function fetchInfoForSiteIDs(array $siteIDs): void
	{
		$siteIDs = ArrayHelper::toInteger($siteIDs, []);

		if (empty($siteIDs))
		{
			return;
		}

		$httpClient = $this->container->httpFactory->makeClient();

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

				$this->logger?->debug(sprintf(
					'Enqueueing site #%d (%s) for information update.',
					$site->id, $site->name
				));

				[$url, $options] = $this->getRequestOptions($site, '/index.php/v1/panopticon/core/update');

				return $httpClient
					// See https://docs.guzzlephp.org/en/stable/quickstart.html#async-requests and https://docs.guzzlephp.org/en/stable/request-options.html
					->getAsync($url, $options)
					->then(
						function (ResponseInterface $response) use ($site) {
							try
							{
								$document = @json_decode($response->getBody()->getContents());
							}
							catch (\Exception $e)
							{
								$document = null;
							}

							$attributes = $document?->data?->attributes;

							if (empty($attributes))
							{
								$this->logger?->notice(sprintf(
									'Could not retrieve information for site #%d (%s). Invalid data returned from API call.',
									$site->id, $site->name
								));

								return;
							}

							$this->logger?->debug(
								sprintf(
									'Retrieved information for site #%d (%s).',
									$site->id,
									$site->name
								)
							);

							$config = new Registry($site->getFieldValue('config') ?: '{}');

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
						},
						function (RequestException $e) use ($site) {
							$this->logger?->error(sprintf(
								'Could not retrieve information for site #%d (%s). The server replied with the following error: %s',
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
	}
}