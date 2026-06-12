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
 * Resolves the effective CMS update action for a site, given the configured update strategy and the
 * current vs latest version. Pure, deterministic logic extracted from the update director tasks.
 *
 * @since 1.4.0
 */
abstract class UpdateActionResolver
{
	/**
	 * @param   string  $updateAction    Configured strategy: none|email|patch|minor|major
	 * @param   string  $currentVersion  The currently installed CMS version
	 * @param   string  $latestVersion   The latest available CMS version
	 *
	 * @return  string  One of: none|email|update
	 */
	public static function resolve(string $updateAction, string $currentVersion, string $latestVersion): string
	{
		switch ($updateAction)
		{
			case "none":
			case "email":
				return $updateAction;

			case "major":
				return "update";

			default:
			case "patch":
				$current = Version::create($currentVersion);
				$latest  = Version::create($latestVersion);

				$shortCurrent = $current->major() . '.' . $current->minor();
				$shortLatest  = $latest->major() . '.' . $latest->minor();

				return $shortCurrent === $shortLatest ? "update" : "email";

			case "minor":
				$current = Version::create($currentVersion);
				$latest  = Version::create($latestVersion);

				return $current->major() === $latest->major() ? "update" : "email";
		}
	}
}
