<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\UsageStats\Collector\Constants\SoftwareType;
use Akeeba\UsageStats\Collector\StatsCollector;
use Awf\Date\Date;
use Awf\Mvc\Model;

/**
 * Anonymous Usage Statistics Collection
 *
 * @since  1.0.3
 */
final class Usagestats extends Model
{
	/**
	 * Collect usage statistics
	 *
	 * @param   bool  $force  Force collection (no checks performed)
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public function collectStatistics(bool $force = false): void
	{
		// We cannot collect statistics if we don't know our version
		if (!defined('AKEEBA_PANOPTICON_VERSION'))
		{
			return;
		}

		// Is statistics collection disabled at the application level?
		if (!$this->isStatsCollectionEnabled())
		{
			return;
		}

		try
		{
			// Get the statistics collection helper
			$statsCollector = $this->getStatsCollector();

			// Forced collection: no checks performed.
			if ($force)
			{
				$statsCollector->sendStatistics();

				return;
			}

			// Conditional collection: are we told to not use the ignored domains?
			$container           = Factory::getContainer();
			$useForbiddenDomains = true;

			if (isset($container['usageStatsIgnoreDomains']))
			{
				$useForbiddenDomains = (bool) $container['usageStatsIgnoreDomains'];
			}

			if (defined('PANOPTICON_USAGE_STATS_IGNORE_DOMAINS'))
			{
				$useForbiddenDomains = (bool) constant('PANOPTICON_USAGE_STATS_IGNORE_DOMAINS');
			}

			// Conditional collection: perform collection
			$statsCollector->conditionalSendStatistics($useForbiddenDomains);
		}
		catch (\Throwable $e)
		{
			return;
		}
	}

	/**
	 * Returns the data which will be sent to the stats collection server
	 *
	 * @return  array|null
	 * @since   1.0.3
	 */
	public function getData(): ?array
	{
		// Is statistics collection disabled at the application level?
		if (!$this->isStatsCollectionEnabled())
		{
			return null;
		}

		$statsCollector = $this->getStatsCollector();

		try
		{
			return $statsCollector->getQueryParameters();
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	/**
	 * Get the statistics collection server URL
	 *
	 * @return  string
	 * @since   1.0.3
	 */
	public function getServerUrl(): string
	{
		return $this->getStatsCollector()->getServerUrl();
	}

	/**
	 * Is the statistics collection enabled?
	 *
	 * @return  bool
	 * @since   1.0.3
	 */
	public function isStatsCollectionEnabled(): bool
	{
		$container = Factory::getContainer();

		return boolval($container->appConfig->get('stats_collection', true) ?? true);
	}

	/**
	 * Get the last date and time usage statistics was collected.
	 *
	 * @return  Date|null
	 * @since   1.0.3
	 */
	public function getLastCollectionDate(): ?Date
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true)
			->select('value')
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('stats_lastrun'));
		$item  = $db->setQuery($query)->loadResult() ?: null;

		if (empty($item))
		{
			return null;
		}

		return $this->getContainer()->dateFactory('@' . $item);
	}

	/**
	 * Reset the installation ID
	 *
	 * @return  void
	 */
	public function resetSID(): void
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__akeeba_common'))
			->where(
				[
					$db->quoteName('key') . ' = ' . $db->quote('stats_siteid'),
					$db->quoteName('key') . ' = ' . $db->quote('stats_siteurl'),
				], 'OR'
			);
		$db->setQuery($query)->execute();
	}

	/**
	 * Set the status of the collection feature
	 *
	 * @param   bool  $status  TRUE to enable, FALSE to disable
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public function setFeatureStatus(bool $status): void
	{
		$appConfig = $this->getContainer()->appConfig;
		$appConfig->set('stats_collection', $status ? 1 : 0);
		$appConfig->saveConfiguration();

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($appConfig->getDefaultPath());
		}
	}

	/**
	 * Get the statistics collector
	 *
	 * @return  StatsCollector
	 * @since   1.0.3
	 */
	private function getStatsCollector(): StatsCollector
	{
		$statsCollector = new StatsCollector(
			SoftwareType::PANOPTICON,
			AKEEBA_PANOPTICON_VERSION
		);

		// Do we have a custom Usage Stats server URL?
		$container = Factory::getContainer();

		if (isset($container['usageStatsUrl']))
		{
			$statsCollector->setServerUrl($container['usageStatsUrl']);
		}

		return $statsCollector;
	}
}