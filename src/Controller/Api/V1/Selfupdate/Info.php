<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Selfupdate;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\Selfupdate as SelfupdateModel;

/**
 * API handler: GET /v1/selfupdate
 *
 * Returns self-update information including available versions, current version, and update status.
 *
 * @since  1.4.0
 */
class Info extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		try
		{
			$updateInfo    = $model->getUpdateInformation(true);
			$latestVersion = $model->getLatestVersion(true);
			$hasUpdate     = $model->hasUpdate(true);
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Failed to retrieve update information: ' . $e->getMessage());

			return;
		}

		$this->sendJsonResponse([
			'hasUpdate'      => $hasUpdate,
			'currentVersion' => defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev',
			'latestVersion'  => $latestVersion?->version,
			'downloadUrl'    => $latestVersion?->downloadUrl,
			'releaseDate'    => $latestVersion?->date,
			'releaseNotes'   => $latestVersion?->releaseNotes,
			'loadedUpdate'   => $updateInfo->loadedUpdate,
			'stuck'          => $updateInfo->stuck,
			'error'          => $updateInfo->error,
		]);
	}
}
