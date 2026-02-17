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
 * API handler: GET /v1/selfupdate/install
 *
 * Extracts the previously downloaded update package into the application root.
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

		try
		{
			$result = $model->extract();
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Installation failed: ' . $e->getMessage());

			return;
		}

		if (!$result)
		{
			$this->sendJsonError(500, 'Installation failed: the update package could not be extracted.');

			return;
		}

		$this->sendJsonResponse(null, message: 'Update package extracted successfully.');
	}
}
