<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/refresh — refresh site information.
 *
 * Synchronous: returns 200 once the refresh task callback completes (mirrors the legacy
 * controller's behaviour which calls `doRefreshSiteInformation()` then redirects with a flash).
 *
 * @since  1.4.0
 */
class Refresh extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesRefresh);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');
		$user = $this->container->userManager->getUser();

		try
		{
			$site->doRefreshSiteInformation();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to refresh site information: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.refresh',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId()
		);

		$this->sendJsonResponse(null, 200, 'Site information refreshed successfully.');
	}
}
