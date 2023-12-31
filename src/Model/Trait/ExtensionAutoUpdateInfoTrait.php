<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Sysconfig;

/**
 * This trait provides methods for determining the auto-update intent for extensions.
 *
 * @since   1.0.6
 */
trait ExtensionAutoUpdateInfoTrait
{
	/**
	 * Caches the update preferences per site ID and extension key.
	 *
	 * @var   array
	 * @since 1.0.6
	 */
	private array $_cacheExtensionUpdatePreferencesBySite = [];

	/**
	 * Caches the global update preferences for extension updates per extension key.
	 *
	 * @var   array|null The global update preferences for extension updates. Null if not set.
	 * @since 1.0.6
	 */
	private ?array $_cacheExtensionUpdatePreferencesGlobal = null;

	/**
	 * Caches the default update preference for extensions.
	 *
	 * @var   string|null
	 * @since 1.0.6
	 */
	private ?string $_cacheExtensionUpdateDefaultPreference = null;

	/**
	 * Determines if an extension will be automatically updated based on its version and update preferences.
	 *
	 * @param   object  $extension  The extension object.
	 * @param   Site    $site       The site object.
	 *
	 * @return  bool Returns true if the extension will auto-update, false otherwise.
	 * @since   1.0.6
	 */
	protected function willExtensionAutoUpdate(object $extension, Site $site): bool
	{
		// Make sure we have an instance of the Sysconfig model which gives us the extension update preferences.
		static $sysConfigModel = null;

		/** @var Sysconfig $sysConfigModel */
		$sysConfigModel ??= Factory::getContainer()->mvcFactory->makeTempModel('Sysconfig');

		// Get the extension key and version information
		$key        = $sysConfigModel->getExtensionShortname(
			$extension->type ?? '', $extension->element ?? '', $extension->folder ?? '', $extension->client_id ?? ''
		);
		$oldVersion = $extension->version?->current ?? null;
		$newVersion = $extension->version?->new ?? null;

		// If we are missing basic info, or if the newest available version is not newer than the installed, quit.
		if (empty($oldVersion) || empty($newVersion) || empty($key) || version_compare($oldVersion, $newVersion, 'ge'))
		{
			return false;
		}

		// Ensure our caches exist (THANK YOU, PHP 8 FOR MAKING THIS EASY!)
		$this->_cacheExtensionUpdatePreferencesBySite[$site->getId()] ??=
			$sysConfigModel->getExtensionPreferencesAndMeta($site->id);
		$this->_cacheExtensionUpdatePreferencesGlobal                 ??=
			$sysConfigModel->getExtensionPreferencesAndMeta();
		$this->_cacheExtensionUpdateDefaultPreference                 ??=
			Factory::getContainer()->appConfig->get('tasks_extupdate_install', 'none');

		// Calculate the applicable update preference for this extension
		$updatePreference = ($this->_cacheExtensionUpdatePreferencesBySite[$site->getId()][$key]?->preference ?? '')
			?: ($this->_cacheExtensionUpdatePreferencesGlobal[$key]?->preference ?? '')
				?: $this->_cacheExtensionUpdateDefaultPreference;

		// Parse the applicable update preference based on version settings
		if (in_array($updatePreference, ['minor', 'patch']))
		{
			$vOld = Version::create($oldVersion);
			$vNew = Version::create($newVersion);
		}

		return match ($updatePreference)
		{
			default => false,
			'major' => true,
			'minor' => $vOld->major() === $vNew->major(),
			'patch' => $vOld->versionFamily() === $vNew->versionFamily(),
		};
	}
}