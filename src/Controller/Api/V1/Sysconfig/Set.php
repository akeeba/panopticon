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
 * API handler: POST /v1/sysconfig/:paramName
 *
 * Sets the value of a single system configuration parameter and saves the configuration.
 *
 * @since  1.4.0
 */
class Set extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireSuperUser();

		$paramName = $this->input->getString('paramName', '');

		if ($paramName === '')
		{
			$this->sendJsonError(400, 'Missing required parameter: paramName');
		}

		$body = $this->getJsonBody();

		if (!array_key_exists('value', $body))
		{
			$this->sendJsonError(400, 'Missing required field in request body: value');
		}

		$this->container->appConfig->set($paramName, $body['value']);

		try
		{
			$this->container->appConfig->saveConfiguration();
		}
		catch (\RuntimeException $e)
		{
			$this->sendJsonError(500, 'Failed to save configuration: ' . $e->getMessage());
		}

		$this->sendJsonResponse([
			'paramName' => $paramName,
			'value'     => $this->container->appConfig->get($paramName),
		]);
	}
}
