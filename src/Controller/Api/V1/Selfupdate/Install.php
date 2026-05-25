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

/**
 * API handler: GET /v1/selfupdate/install
 *
 * Installs the previously-staged update package: extracts the ZIP over the application
 * root, invalidates OPcache for the new PHP files, and clears precompiled Blade
 * templates. Requires {@see Download} to have run first.
 *
 * Super-user only.
 *
 * @since  1.4.0
 */
class Install extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		$stagedPath = $model->resolveUpdatePackagePath();

		if ($stagedPath === null)
		{
			$this->sendJsonError(
				409,
				'No update package has been downloaded. Call /v1/selfupdate/download first.',
				'selfupdate.not_downloaded'
			);

			return;
		}

		$installed = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';
		$latest    = null;

		try
		{
			$latest = $model->getLatestVersion(false);
		}
		catch (\Throwable)
		{
			// Best-effort: we can still try to install. Audit `to_version` will be NULL.
		}

		try
		{
			$model->performInstall($stagedPath);
		}
		catch (RuntimeException $e)
		{
			$this->sendJsonError(
				500,
				'Installation failed: ' . $e->getMessage(),
				'selfupdate.install_failed'
			);

			return;
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(
				500,
				'Installation failed.',
				'selfupdate.install_failed'
			);

			return;
		}

		$user = $this->container->userManager->getUser();
		AuditLog::record(
			'selfupdate.install',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'selfupdate',
			null,
			[
				'from_version' => $installed,
				'to_version'   => $latest?->version,
			]
		);

		$this->sendJsonResponse(null, message: 'Update package installed successfully.');
	}
}
