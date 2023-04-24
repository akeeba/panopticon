<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\Version;
use Joomla\Database\ParameterType;

class CoreModel extends BaseDatabaseModel
{
	private $coreExtensionID = null;

	private $coreUpdateSiteIDs = null;

	use ElementToExtensionIdTrait;

	public function getItem(): ?object
	{
		$mode = $this->getState('panopticon_mode');

		if ($mode === 'core.update')
		{
			$force          = $this->getState('panopticon_force', false);
			$updateInfo     = $this->getJoomlaUpdateInfo($force);
			$updateInfo->id = $this->coreExtensionID ?: 0;

			return $updateInfo;
		}

		return null;
	}

	public function getJoomlaUpdateInfo(bool $force = false): object
	{
		// Get the update parameters from the com_installer configuration
		$params           = ComponentHelper::getComponent('com_installer')->getParams();
		$cacheHours       = (int) $params->get('cachetimeout', 6);
		$cacheTimeout     = 3600 * $cacheHours;
		$minimumStability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);

		$jVersion   = new Version();
		$updateInfo = (object) [
			'current'             => $jVersion->getShortVersion(),
			'latest'              => $jVersion->getShortVersion(),
			'details'             => null,
			'info'                => null,
			'changelog'           => null,
			'extensionAvailable'  => true,
			'updateSiteAvailable' => true,
			'maxCacheHours'       => $cacheHours,
			'minimumStability'    => $minimumStability,
			'updateSiteUrl'       => null,
			'lastUpdateTimestamp' => null,
		];

		// Get the file_joomla pseudo-extension's ID
		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			$updateInfo->extensionAvailable  = false;
			$updateInfo->updateSiteAvailable = false;

			return $updateInfo;
		}

		// Get the IDs of the update sites for the core
		$updateSiteIDs = $this->getCoreUpdateSiteIDs();

		if (empty($updateSiteIDs))
		{
			$updateInfo->updateSiteAvailable = false;

			return $updateInfo;
		}

		// Update updateSiteUrl and lastUpdateTimestamp from the first update site for the core pseudo-extension
		/** @var MVCFactory $comInstallerFactory */
		$comInstallerFactory = Factory::getApplication()->bootComponent('com_installer')->getMVCFactory();
		/** @var \Joomla\Component\Installer\Administrator\Table\UpdatesiteTable $updateSiteTable */
		$updateSiteTable = $comInstallerFactory->createTable('Updatesite');
		if ($updateSiteTable->load(array_reduce($updateSiteIDs, fn(int $carry, int $item) => min($carry, $item), PHP_INT_MAX)))
		{
			$updateInfo->updateSiteUrl = $updateSiteTable->location;
			$updateInfo->lastUpdateTimestamp = $updateSiteTable->last_check_timestamp ?: 0;
		}

		// The $force flag tells us to remove update information before fetching the core update status
		if ($force)
		{
			$this->forceCoreUpdateRefresh();
		}

		$hasUpdate = Updater::getInstance()->findUpdates($eid, $cacheTimeout, $minimumStability, true);

		if (!$hasUpdate)
		{
			//return $updateInfo;
		}

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'version',
				'detailsurl',
				'infourl',
				'changelogurl',
			]))
			->from($db->quoteName('#__updates'))
			->where($db->quoteName('extension_id') . ' = :eid')
			->order($db->quoteName('update_id') . ' DESC')
			->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$latest = $db->setQuery($query, 0, 1)->loadObject() ?: null;
		}
		catch (\Throwable $e)
		{
			$latest = null;
		}

		if (is_object($latest))
		{
			$updateInfo->latest    = $latest->version;
			$updateInfo->details   = $latest->detailsurl;
			$updateInfo->info      = $latest->infourl;
			$updateInfo->changelog = $latest->changelogurl;
		}

		return $updateInfo;
	}

	private function getCoreExtensionID(): int
	{
		return $this->coreExtensionID =
			$this->coreExtensionID ?? (
		$this->getExtensionIdFromElement('files_joomla') ?: 0
		);
	}

	private function getCoreUpdateSiteIDs(): array
	{
		if ($this->coreUpdateSiteIDs !== null)
		{
			return $this->coreUpdateSiteIDs;
		}

		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			$this->coreUpdateSiteIDs = [];

			return $this->coreUpdateSiteIDs;
		}

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('update_site_id'))
			->from($db->quoteName('#__update_sites_extensions'))
			->where($db->quoteName('extension_id') . ' = :eid')
			->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$this->coreUpdateSiteIDs = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (\Throwable $e)
		{
			$this->coreUpdateSiteIDs = [];
		}

		if (empty($this->coreUpdateSiteIDs))
		{
			return $this->coreUpdateSiteIDs;
		}

		// Get enabled core update sites.
		$query = $db->getQuery(true)
			->select($db->quoteName('update_site_id'))
			->from($db->quoteName('#__update_sites'))
			->whereIn($db->quoteName('update_site_id'), $this->coreUpdateSiteIDs, ParameterType::INTEGER)
			->where($db->quoteName('enabled') . ' = 1');

		try
		{
			$this->coreUpdateSiteIDs = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (\Exception $e)
		{
			$this->coreUpdateSiteIDs = [];
		}

		return $this->coreUpdateSiteIDs;
	}

	private function forceCoreUpdateRefresh(): void
	{
		// Get the core extension ID
		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			return;
		}

		// Get the core update site IDs
		$updateSiteIDs = $this->getCoreUpdateSiteIDs();

		if (empty($updateSiteIDs))
		{
			return;
		}

		// Get a database object
		$db = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();

		// Clear update records
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__updates'))
			->where($db->quoteName('extension_id') . ' = :eid')
			->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			// Swallow the exception.
		}

		// Reset last check timestamp on update site
		$query = $db->getQuery(true)
			->update($db->quoteName('#__update_sites'))
			->set($db->quoteName('last_check_timestamp') . ' = 0')
			->whereIn($db->quoteName('update_site_id'), $updateSiteIDs, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			// Swallow the exception.
		}

	}
}