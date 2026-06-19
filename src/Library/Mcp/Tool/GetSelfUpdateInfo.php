<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Model\Selfupdate as SelfupdateModel;

/**
 * MCP tool: Panopticon self-update status.
 *
 * Mirrors `GET /api/v1/selfupdate` (Super User only). Reports the installed and latest available Panopticon versions.
 *
 * @since  2.2.0
 */
class GetSelfUpdateInfo extends AbstractTool
{
	public function getName(): string
	{
		return 'get_selfupdate_info';
	}

	public function getDescription(): string
	{
		return 'Report whether a Panopticon application update is available: the installed version, the latest '
			. 'version, the release date and notes. Super User only.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SelfupdateRead;
	}

	public function isSuperUserOnly(): bool
	{
		return true;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'force' => [
					'type'        => 'boolean',
					'description' => 'Force a fresh check of the update channel instead of using the cached result.',
				],
			],
		];
	}

	public function __invoke(bool $force = false): array
	{
		$this->assertSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		if ($force)
		{
			$model->bustCache();
		}

		try
		{
			$updateInfo    = $model->getUpdateInformation($force);
			$latestVersion = $model->getLatestVersion(false);
			$hasUpdate     = $model->hasUpdate(false);
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to retrieve update information: ' . $e->getMessage());
		}

		$installed = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';

		return [
			'installed_version' => $installed,
			'latest_version'    => $latestVersion?->version,
			'has_update'        => $hasUpdate,
			'release_date'      => $latestVersion?->date,
			'stuck'             => $updateInfo->stuck,
			'error'             => $updateInfo->error,
		];
	}
}
