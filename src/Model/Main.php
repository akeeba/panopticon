<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Cache\CallbackController;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\SoftwareVersions\PhpVersion;
use Awf\Date\Date;
use Awf\Input\Input;
use Awf\Mvc\Model;
use Throwable;

/**
 * Main Page Model. Used to get the sites overview information.
 *
 * @since  1.0.0
 */
class Main extends Model
{
	public function getBestLayout(Input $input): string
	{
		$user           = $this->getContainer()->userManager->getUser();
		$userPreference = $user?->getParameters()?->get('main_layout', 'default');
		$urlLayout      = $input->get('layout', null);
		$storedLayout   = $this->getContainer()->segment->get('main.layout', null);

		// This prevents using layout=cron from getting us stuck on that page :p
		if (!in_array($urlLayout, ['default', 'dashboard']))
		{
			$urlLayout = null;
		}

		// No layout in the URL, nor stored in the session. Return the user preference.
		if ($urlLayout === null && $storedLayout === null)
		{
			return $userPreference ?? 'default';
		}

		// No layout in the URL, but a stored layout exists. Return it.
		if ($urlLayout === null)
		{
			return $storedLayout;
		}


		// A layout is specified in the URL. Store it and return it.
		$this->getContainer()->segment->set('main.layout', $urlLayout);

		if ($urlLayout === 'default' && $storedLayout !== null && $storedLayout !== 'default')
		{
			$input->set('limitstart', $input->getInt('limitstart', 0));
		}

		return $urlLayout;
	}

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
			return $this->container->dateFactory($lastExecution);
		}
		catch (Throwable)
		{
			return null;
		}
	}

	/**
	 * Are the CRON jobs working?
	 *
	 * We consider them to be working when there is a CRON job which has executed within the last minute.
	 *
	 * @return  bool
	 * @since   1.0.5
	 */
	public function areCRONJobsWorking(): bool
	{
		$lastExecutionTime = $this->getLastCronExecutionTime();

		if (empty($lastExecutionTime))
		{
			return false;
		}

		$lastPlausibleRun = ($this->container->dateFactory())->sub(new \DateInterval('PT55S'));
		$lastPlausibleRun->setTime($lastPlausibleRun->hour, $lastPlausibleRun->minute, 0, 0);

		return $lastPlausibleRun->diff($lastExecutionTime)->invert === 0;
	}

	/**
	 * How far behind is the execution of CRON jobs, on average?
	 *
	 * @param   int  $threshold  The minimum number of seconds to report.
	 *
	 * @return  int|null  NULL if CRON jobs are not working
	 * @since   1.0.5
	 */
	public function getCRONJobsSecondsBehind(int $threshold = 120): ?int
	{
		if (!$this->areCRONJobsWorking())
		{
			return null;
		}

		$now        = time();
		$db         = $this->getContainer()->db;
		$query      = $db->getQuery(true)
			->select($db->quoteName('next_execution'))
			->from('#__tasks')
			->where(
				[
					$db->quoteName('enabled') . ' = 1',
					$db->quoteName('next_execution') . ' < UNIX_TIMESTAMP()',
				]
			);
		$timestamps = $db->setQuery($query)->loadColumn();

		if (empty($timestamps))
		{
			return 0;
		}

		$timestamps = array_map(
			fn(string $stamp) => $now - (new \DateTime($stamp))->format('U'),
			$timestamps
		);

		$averageDelay = (int) ceil(array_sum($timestamps) / count($timestamps));

		if ($averageDelay < $threshold)
		{
			return 0;
		}

		return $averageDelay;
	}

	public function getKnownCMSVersions(?string $siteType = null): array
	{
		/** @var Container $container */
		$container = $this->container;

		$cacheController = new CallbackController(
			container: $container,
			pool: $container->cacheFactory->pool('system'),
		);

		return $cacheController->get(
			callback: function () use ($siteType): array {
				$db    = $this->container->db;
				$query = $db->getQuery(true);
				$query
					->select(
						[
							'DISTINCT SUBSTR(SUBSTRING_INDEX(' .
							$query->jsonExtract($db->quoteName('config'), '$.core.current.version') .
							', ' . $db->quote('.') . ', 2) FROM 2) AS ' .
							$db->quoteName('version'),
							'IFNULL(' .
							$query->jsonExtract($db->quoteName('config'), '$.cmsType') .
							',' .
							$db->quote(CMSType::JOOMLA->value) .
							') AS ' . $db->quoteName('cmsType'),
						]
					)
					->from($db->quoteName('#__sites'))
					->where($db->quoteName('enabled') . ' = 1')
					->order($db->quoteName('version') . ' DESC');

				if ($siteType === CMSType::JOOMLA->value)
				{
					$query->where(
						'(' .
						$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote(
							CMSType::JOOMLA->value
						) .
						' OR ' .
						$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' IS NULL' .
						')'
					);
				}
				elseif (!empty($siteType))
				{
					$query->where(
						$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote($siteType)
					);
				}

				$versions = $db->setQuery($query)->loadObjectList();

				if (empty($versions))
				{
					return [];
				}

				// Remove sites which have failed to load.
				$versions = array_filter($versions);

				// Separate per CMS
				$temp = [];

				foreach ($versions as $versionObject)
				{
					$type          = trim((string) $versionObject->cmsType, '"');
					$temp[$type]   ??= [];
					$temp[$type][] = $versionObject->version;
				}

				$types = array_keys($temp);

				foreach ($types as $type)
				{
					uasort(
						$temp[$type], function ($a, $b): void {
						version_compare($a ?? '0.0.0', $b ?? '0.0.0');
					}
					);
				}

				asort($types);

				$versions = [];

				foreach ($types as $type)
				{
					foreach ($temp[$type] as $item)
					{
						$versions[$type . '.' . $item] = (CMSType::tryFrom($type)?->forHumans() ?? '(???)') . ' '
						                                 . $item;
					}
				}

				return $versions;
			},
			id: 'known_' . ($siteType ?? 'all_cms') . '_versions',
			expiration: 60
		);
	}

	public function getKnownPHPVersions(): array
	{
		$phpVersion = new PhpVersion($this->container);

		$versions = array_keys($phpVersion->getPhpEolInformation());

		return array_combine($versions, $versions);
	}

	public function getSiteNamesForSelect(bool $enabled = true, ?string $emptyLabel = null): array
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('name'),
				]
			)
			->from($db->quoteName('#__sites'))
			->order($db->quoteName('name') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		$ret = $db->setQuery($query)->loadAssocList('id', 'name') ?: [];

		if (!empty($emptyLabel))
		{
			$ret = array_combine(
				array_merge([''], array_keys($ret)),
				array_merge([$this->getLanguage()->text($emptyLabel)], array_values($ret))
			);
		}

		return $ret;
	}
}