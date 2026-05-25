<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Selfupdate;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Selfupdate as SelfupdateModel;

/**
 * API handler: GET /v1/selfupdate
 *
 * Returns the self-update status for the running Panopticon instance: currently installed
 * version, latest available version, update availability flag, and release metadata. Hits
 * the update channel only if the on-disk cache is stale (or when `?force=1` is passed).
 *
 * Super-user only.
 *
 * @since  1.4.0
 */
class Info extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$force = (bool) $this->input->getInt('force', 0);

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
			$this->sendJsonError(
				500,
				'Failed to retrieve update information: ' . $e->getMessage(),
				'selfupdate.info_failed'
			);

			return;
		}

		$installed = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';

		$user = $this->container->userManager->getUser();
		AuditLog::record(
			'selfupdate.info',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'selfupdate',
			null,
			[
				'from_version' => $installed,
				'to_version'   => $latestVersion?->version,
				'force'        => $force,
			]
		);

		$this->sendJsonResponse([
			'installed_version'  => $installed,
			'latest_version'     => $latestVersion?->version,
			'has_update'         => $hasUpdate,
			'release_date'       => $latestVersion?->date,
			'release_notes_url'  => null,
			'release_notes'      => $latestVersion?->releaseNotes,
			'download_url'       => $latestVersion?->downloadUrl,
			'loaded_update'      => $updateInfo->loadedUpdate,
			'stuck'              => $updateInfo->stuck,
			'error'              => $updateInfo->error,
		]);
	}
}
