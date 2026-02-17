<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;

/**
 * API handler: GET /v1/sysconfig
 *
 * Returns all system configuration key-value pairs.
 *
 * @since  1.4.0
 */
class GetList extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$this->sendJsonResponse(
			$this->container->appConfig->flatten('.')
		);
	}
}
