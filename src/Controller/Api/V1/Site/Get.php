<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;

/**
 * API handler for GET /v1/site/:id — get full site details.
 *
 * @since  1.4.0
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');

		$config = $site->getConfig();

		$this->sendJsonResponse([
			'id'          => $site->getId(),
			'name'        => $site->name,
			'url'         => $site->url,
			'baseUrl'     => $site->getBaseUrl(),
			'enabled'     => (bool) $site->enabled,
			'cmsType'     => $site->cmsType()->value,
			'created_on'  => $site->created_on?->toISO8601() ?? null,
			'created_by'  => $site->created_by,
			'modified_on' => $site->modified_on?->toISO8601() ?? null,
			'modified_by' => $site->modified_by,
			'notes'       => $site->notes,
			'config'      => $config->toObject(),
		]);
	}
}
