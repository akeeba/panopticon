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
 * API handler: GET /v1/selfupdate/download
 *
 * Downloads the latest update package.
 *
 * @since  1.4.0
 */
class Download extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		try
		{
			$filePath = $model->download();
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Download failed: ' . $e->getMessage());

			return;
		}

		$this->sendJsonResponse([
			'filePath' => $filePath,
		], message: 'Update package downloaded successfully.');
	}
}
