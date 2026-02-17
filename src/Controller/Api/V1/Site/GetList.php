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
use Akeeba\Panopticon\Model\Site;

/**
 * API handler for GET /v1/sites — list sites with optional filtering and pagination.
 *
 * @since  1.4.0
 */
class GetList extends AbstractApiHandler
{
	public function handle(): void
	{
		/** @var Site $model */
		$model = $this->container->mvcFactory->makeTempModel('Site');

		// Apply filters from query parameters
		$search  = $this->input->getString('search', null);
		$enabled = $this->input->get('enabled', null);
		$cmsType = $this->input->getString('cmsType', null);

		if ($search !== null)
		{
			$model->setState('search', $search);
		}

		if ($enabled !== null)
		{
			$model->setState('enabled', (int) $enabled);
		}

		if ($cmsType !== null)
		{
			$model->setState('cmsType', $cmsType);
		}

		// Pagination
		$limit  = max(0, $this->input->getInt('limit', 50));
		$offset = max(0, $this->input->getInt('offset', 0));

		$model->setState('limitstart', $offset);
		$model->setState('limit', $limit);

		$items = $model->get(true);
		$total = $model->count();

		$result = [];

		/** @var Site $item */
		foreach ($items as $item)
		{
			$config = $item->getConfig();

			$result[] = [
				'id'      => $item->getId(),
				'name'    => $item->name,
				'url'     => $item->getBaseUrl(),
				'enabled' => (bool) $item->enabled,
				'cmsType' => $item->cmsType()->value,
			];
		}

		$this->sendJsonResponse(
			$result,
			pagination: [
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			]
		);
	}
}
