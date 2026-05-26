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

/**
 * API handler for GET /v1/site/:id/extension/:extId/downloadkey — read an extension's download key info.
 *
 * Returns the data verbatim per the master plan's no-redaction decision (§8); the existing UI
 * `Sites::dlkey()` already exposes the same fields to anyone holding `panopticon.admin`.
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeyGet extends AbstractApiHandler
{
	public function handle(): void
	{
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'admin');
		$user  = $this->container->userManager->getUser();

		if ($site->cmsType() !== CMSType::JOOMLA)
		{
			$this->sendJsonError(422, 'Download keys are only supported on Joomla sites.', 'site.wrong_cms');
		}

		$extensions = (array) $site->getConfig()->get('extensions.list');

		if (!array_key_exists($extId, $extensions))
		{
			$this->sendJsonError(404, 'Extension not found on this site.', 'extension.not_found');
		}

		$extension = $extensions[$extId];
		$dlKeyInfo = $extension->downloadkey ?? null;

		AuditLog::record(
			'site.extension.downloadkey.get',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['extensionId' => $extId]
		);

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
