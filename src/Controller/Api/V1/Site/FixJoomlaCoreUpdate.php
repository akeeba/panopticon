<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/fixjoomlacoreupdate — clear a stuck Joomla core-update flag.
 *
 * Delegates to `Model\Site::fixCoreUpdateSite()` which the legacy controller already calls.
 *
 * @since  1.4.0
 */
class FixJoomlaCoreUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesCmsUpdate);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'admin');
		$user = $this->container->userManager->getUser();

		if ($site->cmsType() !== CMSType::JOOMLA)
		{
			$this->sendJsonError(
				422,
				'This operation is only available for Joomla sites.',
				'site.wrong_cms'
			);
		}

		try
		{
			$site->fixCoreUpdateSite();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to fix the Joomla core update site: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.fix_joomla_core_update',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId()
		);

		$this->sendJsonResponse(null, 200, 'Joomla core update site fixed successfully.');
	}
}
