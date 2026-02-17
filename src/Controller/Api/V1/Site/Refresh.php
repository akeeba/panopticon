<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Task\RefreshSiteInfo;
use Awf\Registry\Registry;
use stdClass;

/**
 * API handler for POST /v1/site/:id/refresh — refresh site information.
 *
 * @since  1.4.0
 */
class Refresh extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');

		try
		{
			/** @var RefreshSiteInfo $callback */
			$callback = $this->container->taskRegistry->get('refreshsiteinfo');
			$dummy    = new stdClass();
			$registry = new Registry();

			$registry->set('limitStart', 0);
			$registry->set('limit', 1);
			$registry->set('force', true);
			$registry->set('filter.ids', [$site->getId()]);

			do
			{
				$return = $callback($dummy, $registry);
			}
			while ($return === Status::WILL_RESUME->value);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to refresh site information: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Site information refreshed successfully.');
	}
}
