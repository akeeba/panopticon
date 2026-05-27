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
 * API handler for POST /v1/site/:id/extension/:extId/downloadkey — set the download key for an extension.
 *
 * Delegates to `Model\Site::saveDownloadKey()` (the same code path as the legacy
 * `Controller\Sites::savedlkey()`). The model already validates that the extension supports
 * download keys and pushes the value to the remote connector.
 *
 * Request body:
 *   { "key": "<download-key-value>" }
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeySet extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesExtensions);
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'admin');
		$user  = $this->container->userManager->getUser();

		if ($site->cmsType() !== CMSType::JOOMLA)
		{
			$this->sendJsonError(422, 'Download keys are only supported on Joomla sites.', 'site.wrong_cms');
		}

		$body = $this->getJsonBody();

		if (!array_key_exists('key', $body))
		{
			$this->sendJsonError(400, 'The "key" field is required in the request body.', 'validation.bad_request');
		}

		$key = $body['key'];

		if ($key !== null && !is_string($key))
		{
			$this->sendJsonError(400, 'The "key" field must be a string or null.', 'validation.bad_request');
		}

		// Validate the extension exists before delegating, so we can return a specific code.
		$extensions = (array) $site->getConfig()->get('extensions.list');

		if (!array_key_exists($extId, $extensions))
		{
			$this->sendJsonError(404, 'Extension not found on this site.', 'extension.not_found');
		}

		try
		{
			$site->saveDownloadKey($extId, $key);
		}
		catch (\RuntimeException $e)
		{
			// `saveDownloadKey()` throws for "extension does not support download keys" and remote
			// rejections. Treat as 422 — the request was well-formed but the world rejects it.
			$this->sendJsonError(422, $e->getMessage(), 'extension.invalid_download_key');
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to save download key: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extension.downloadkey.set',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['extensionId' => $extId]
		);

		$this->sendJsonResponse(null, 200, 'Download key saved successfully.');
	}
}
