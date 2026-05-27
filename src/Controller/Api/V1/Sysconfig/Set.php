<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Sysconfig as SysconfigModel;
use RuntimeException;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: POST /v1/sysconfig/:paramName
 *
 * Sets the value of a single system configuration parameter and saves the configuration.
 * Super-user only. Sensitive keys cannot be written via the API.
 *
 * @since  1.4.0
 */
class Set extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SysconfigWrite);
		$this->requireSuperUser();

		$paramName = $this->input->getString('paramName', '');

		if ($paramName === '')
		{
			$this->sendJsonError(400, 'Missing required parameter: paramName', 'validation.bad_request');
		}

		/** @var SysconfigModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Sysconfig');

		// Sensitive keys are write-blocked through the API. Returning 403 here is OK because
		// the caller obviously already knew the key name — no enumeration concern.
		if ($model->isSensitiveKey($paramName))
		{
			$this->sendJsonError(
				403,
				'This configuration parameter cannot be modified through the API.',
				'auth.forbidden'
			);
		}

		if (!$model->isKnownKey($paramName))
		{
			$this->sendJsonError(
				404,
				sprintf('Unknown configuration parameter "%s".', $paramName),
				'sysconfig.unknown_param'
			);
		}

		$body = $this->getJsonBody();

		if (!array_key_exists('value', $body))
		{
			$this->sendJsonError(
				400,
				'Missing required field in request body: value',
				'validation.bad_request'
			);
		}

		try
		{
			$newValue = $model->setKey($paramName, $body['value']);
		}
		catch (RuntimeException $e)
		{
			if ($e->getCode() === 400)
			{
				$this->sendJsonError(422, $e->getMessage(), 'sysconfig.invalid_value');
			}

			$this->sendJsonError(500, $e->getMessage());
		}

		$user = $this->container->userManager->getUser();

		AuditLog::record(
			'sysconfig.set',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'sysconfig',
			null,
			['param' => $paramName]
		);

		$this->sendJsonResponse([
			$paramName => $newValue,
		]);
	}
}
