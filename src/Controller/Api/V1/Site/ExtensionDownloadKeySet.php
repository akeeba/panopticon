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
 * API handler for POST /v1/site/:id/extension/:extId/downloadkey — set a download key for an extension.
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeySet extends AbstractApiHandler
{
	public function handle(): void
	{
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'admin');

		$body = $this->getJsonBody();

		if (!array_key_exists('key', $body))
		{
			$this->sendJsonError(400, 'The key field is required in the request body.');
		}

		$key = $body['key'];

		try
		{
			$site->saveDownloadKey($extId, $key);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to save download key: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Download key saved successfully.');
	}
}
