<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Library\Version;

defined('AKEEBA') || die;

/**
 * Pure decision helper: should a given extension auto-update?
 *
 * Extracted from ExtensionAutoUpdateInfoTrait so the logic can be unit-tested
 * without a container or database.
 *
 * @since 1.1.0
 */
abstract class ExtensionAutoUpdateResolver
{
	/**
	 * Decide whether an extension should auto-update, given the applicable preference and the
	 * installed vs available versions. Pure logic extracted from ExtensionAutoUpdateInfoTrait.
	 *
	 * @param   string  $preference   none|major|minor|patch (anything else => false)
	 * @param   string  $oldVersion   Installed version
	 * @param   string  $newVersion   Available version
	 */
	public static function willAutoUpdate(string $preference, string $oldVersion, string $newVersion): bool
	{
		// Not actually an upgrade => never auto-update.
		if ($oldVersion === '' || $newVersion === '' || version_compare($oldVersion, $newVersion, 'ge'))
		{
			return false;
		}

		if (!in_array($preference, ['minor', 'patch'], true))
		{
			return $preference === 'major';
		}

		$vOld = Version::create($oldVersion);
		$vNew = Version::create($newVersion);

		return match ($preference)
		{
			'minor' => $vOld->major() === $vNew->major(),
			'patch' => $vOld->versionFamily() === $vNew->versionFamily(),
		};
	}
}
