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
use RuntimeException;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: GET /v1/selfupdate/download
 *
 * Downloads the latest available update package into the local staging area
 * (typically `<APATH_TMP>/update.zip`). Returns the resolved path plus the file
 * size and SHA-256 hash on success.
 *
 * Super-user only.
 *
 * @since  1.4.0
 */
class Download extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SelfupdateWrite);
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		try
		{
			$latest = $model->getLatestVersion(false);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(
				502,
				'Could not retrieve update information: ' . $e->getMessage(),
				'selfupdate.download_failed'
			);

			return;
		}

		try
		{
			$path = $model->download();
		}
		catch (RuntimeException $e)
		{
			// "No available update" path — model throws when hasUpdate() is false.
			$this->sendJsonError(409, $e->getMessage(), 'selfupdate.no_update_available');

			return;
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(
				502,
				'Download failed: ' . $e->getMessage(),
				'selfupdate.download_failed'
			);

			return;
		}

		$size   = @filesize($path) ?: null;
		$sha256 = is_file($path) ? @hash_file('sha256', $path) : null;

		$installed = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';

		$user = $this->container->userManager->getUser();
		AuditLog::record(
			'selfupdate.download',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'selfupdate',
			null,
			[
				'from_version' => $installed,
				'to_version'   => $latest?->version,
				'size'         => $size,
			]
		);

		$this->sendJsonResponse(
			[
				'path'   => basename($path),
				'size'   => $size,
				'sha256' => $sha256 ?: null,
			],
			message: 'Update package downloaded successfully.'
		);
	}
}
