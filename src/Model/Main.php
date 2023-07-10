<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;
use Awf\Date\Date;
use Awf\Mvc\Model;

class Main extends Model
{
	public function getHighestJoomlaVersion(): ?string
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true);
		$query->selectDistinct(
			$query->jsonExtract($db->quoteName('config'), '$.core.latest.version')
		)->from($db->quoteName('#__sites'));

		$versions = $db->setQuery($query)->loadColumn(0) ?: [];

		return array_reduce(
			$versions,
			fn(?string $carry, string $item) => $carry === null
				? $item
				: (version_compare($carry, $item, 'lt') ? $item : $carry),
			null
		);
	}

	public function getLastCronExecutionTime(): ?Date
	{
		$db = $this->container->db;
		$db->lockTable('#__akeeba_common');
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('panopticon.task.last.execution'));

		$lastExecution = $db->setQuery($query)->loadResult();

		$db->unlockTables();

		if (empty($lastExecution))
		{
			return null;
		}

		try
		{
			return new Date($lastExecution);
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	public function getKnownJoomlaVersions(): array
	{
		/** @var Container $container */
		$container = $this->container;

		$cacheController = new CallbackController(
			container: $container,
			pool: $container->cacheFactory->pool('system'),
		);

		return $cacheController->get(
			callback: function (): array {
				$db       = $this->container->db;
				$query    = $db->getQuery(true);
				$query
					->select(
						'DISTINCT SUBSTR(SUBSTRING_INDEX(' .
						$query->jsonExtract($db->quoteName('config'), '$.core.current.version') .
						', ' . $db->quote('.') . ', 2) FROM 2) AS ' .
						$db->quoteName('version')
					)
					->from($db->quoteName('#__sites'))
					->where($db->quoteName('enabled') . ' = 1')
					->order($db->quoteName('version') . ' DESC');
				$versions = $db->setQuery($query)->loadColumn();

				if (empty($versions))
				{
					return [];
				}

				uasort($versions, fn($a, $b) => version_compare($a ?? '', $b ?? ''));

				return array_combine($versions, $versions);
			},
			id: 'known_joomla_versions',
			expiration: 60
		);
	}

	public function getKnownPHPVersions(): array
	{
		$phpVersion = new PhpVersion($this->container);

		$versions = array_keys($phpVersion->getPhpEolInformation());

		return array_combine($versions, $versions);
	}
}