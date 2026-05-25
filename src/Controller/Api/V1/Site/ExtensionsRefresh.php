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

/**
 * API handler for POST /v1/site/:id/extensions — synchronously refresh installed-extension info.
 *
 * Delegates to `Model\Site::doRefreshExtensionsInformation()` which the legacy controller
 * `Sites::refreshExtensionsInformation()` also calls. Synchronous: may take several seconds.
 *
 * @since  1.4.0
 */
class ExtensionsRefresh extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');
		$user = $this->container->userManager->getUser();

		try
		{
			$site->doRefreshExtensionsInformation(true, true);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to refresh extensions information: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extensions.refresh',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId()
		);

		$this->sendJsonResponse(null, 200, 'Extensions information refreshed successfully.');
	}
}
