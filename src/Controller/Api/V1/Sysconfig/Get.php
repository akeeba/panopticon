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
 * API handler: GET /v1/sysconfig/:paramName
 *
 * Returns the value of a single system configuration parameter.
 *
 * @since  1.4.0
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$paramName = $this->input->getString('paramName', '');

		if ($paramName === '')
		{
			$this->sendJsonError(400, 'Missing required parameter: paramName');
		}

		$value = $this->container->appConfig->get($paramName);

		$this->sendJsonResponse([
			'paramName' => $paramName,
			'value'     => $value,
		]);
	}
}
