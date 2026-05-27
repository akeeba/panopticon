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
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: GET /v1/selfupdate/postinstall
 *
 * Runs post-install bookkeeping: updates the database schema, re-checks default tasks,
 * removes obsolete files/folders, clears related cache pools, and reloads the update
 * cache. Safe to call repeatedly.
 *
 * Super-user only.
 *
 * @since  1.4.0
 */
class Postinstall extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SelfupdateWrite);
		$this->requireSuperUser();

		/** @var SelfupdateModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		$installed = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';

		try
		{
			$model->postUpdate();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(
				500,
				'Post-installation failed: ' . $e->getMessage(),
				'selfupdate.postinstall_failed'
			);

			return;
		}

		$user = $this->container->userManager->getUser();
		AuditLog::record(
			'selfupdate.postinstall',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'selfupdate',
			null,
			[
				'from_version' => $installed,
			]
		);

		$this->sendJsonResponse(null, message: 'Post-installation completed successfully.');
	}
}
