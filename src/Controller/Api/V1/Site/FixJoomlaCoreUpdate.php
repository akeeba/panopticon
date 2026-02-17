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

/**
 * API handler for POST /v1/site/:id/fixjoomlacoreupdate — fix the Joomla core update site.
 *
 * @since  1.4.0
 */
class FixJoomlaCoreUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');

		if ($site->cmsType() !== CMSType::JOOMLA)
		{
			$this->sendJsonError(400, 'This operation is only available for Joomla sites.');
		}

		try
		{
			$site->fixCoreUpdateSite();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to fix core update site: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Core update site fixed successfully.');
	}
}
