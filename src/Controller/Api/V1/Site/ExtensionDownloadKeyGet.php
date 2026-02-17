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
 * API handler for GET /v1/site/:id/extension/:extId/downloadkey — get download key info for an extension.
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeyGet extends AbstractApiHandler
{
	public function handle(): void
	{
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'read');

		$extensions = (array) $site->getConfig()->get('extensions.list');

		if (!array_key_exists($extId, $extensions))
		{
			$this->sendJsonError(404, 'Extension not found.');
		}

		$extension = $extensions[$extId];
		$dlKeyInfo = $extension->downloadkey ?? null;

		$this->sendJsonResponse([
			'extensionId' => $extId,
			'name'        => $extension->description ?? $extension->name ?? '',
			'downloadkey' => [
				'supported'   => (bool) ($dlKeyInfo->supported ?? false),
				'valid'       => (bool) ($dlKeyInfo->valid ?? false),
				'prefix'      => $dlKeyInfo->prefix ?? '',
				'suffix'      => $dlKeyInfo->suffix ?? '',
				'updatesites' => $dlKeyInfo->updatesites ?? [],
				'value'       => $dlKeyInfo->value ?? '',
			],
		]);
	}
}
