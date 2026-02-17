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
 * API handler: GET /v1/selfupdate/postinstall
 *
 * Performs post-installation tasks: invalidates PHP OPcache for updated files, clears compiled
 * templates, and runs the post-update routine (database schema, default tasks, cleanup).
 *
 * @since  1.4.0
 */
class Postinstall extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		try
		{
			// Determine the update package path for OPcache invalidation
			if (defined('APATH_TMP') && is_dir(APATH_TMP) && is_file(APATH_TMP . '/update.zip'))
			{
				$zipPath = APATH_TMP . '/update.zip';
			}
			elseif (is_file(sys_get_temp_dir() . '/update.zip'))
			{
				$zipPath = sys_get_temp_dir() . '/update.zip';
			}
			else
			{
				$zipPath = null;
			}

			// Invalidate OPcache for updated PHP files
			if ($zipPath !== null)
			{
				$model->invalidatePHPFiles($zipPath);
			}

			// Clear compiled Blade templates
			$model->clearCompiledTemplates();

			// Run the post-update routine (database schema, default tasks, cleanup, cache)
			$model->postUpdate();
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(500, 'Post-installation failed: ' . $e->getMessage());

			return;
		}

		$this->sendJsonResponse(null, message: 'Post-installation completed successfully.');
	}
}
