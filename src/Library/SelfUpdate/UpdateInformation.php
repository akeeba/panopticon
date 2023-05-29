<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SelfUpdate;

defined('AKEEBA') || die;

class UpdateInformation
{
	public bool $loadedUpdate = false;

	public bool $stuck = true;

	public ?string $error = null;

	public ?string $errorLocation = null;

	public ?string $errorTraceString = null;

	/** @var VersionInformation[] Array of found versions */
	public array $versions = [];

	public function populateVersionsFromGitHubReleases(array $releases): void
	{
		$this->versions = [];

		foreach ($releases as $release)
		{
			if (!is_object($release))
			{
				continue;
			}

			$version = VersionInformation::fromGitHubRelease($release);

			if (empty($version->version))
			{
				continue;
			}

			$this->versions[$version->version] = $version;
		}

		uksort($this->versions, fn($a, $b) => -1 * version_compare($a, $b));
	}
}