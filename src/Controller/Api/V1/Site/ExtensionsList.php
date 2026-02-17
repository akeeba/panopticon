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
 * API handler for GET /v1/site/:id/extensions — list site extensions.
 *
 * @since  1.4.0
 */
class ExtensionsList extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'read');

		$extensions = $site->getExtensionsList();
		$quickInfo  = $site->getExtensionsQuickInfo($extensions);

		$this->sendJsonResponse([
			'extensions' => $extensions,
			'quickInfo'  => $quickInfo,
		]);
	}
}
