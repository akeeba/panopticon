<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Sysconfig;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\Sysconfig as SysconfigModel;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler: GET /v1/sysconfig/:paramName
 *
 * Returns the value of a single non-sensitive system configuration parameter.
 *
 * Sensitive keys yield 404 / `sysconfig.unknown_param` — the same response as an
 * unknown key — so that callers cannot probe for their existence.
 *
 * @since  1.4.0
 */
class Get extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SysconfigRead);
		$this->requireSuperUser();

		$paramName = $this->input->getString('paramName', '');

		if ($paramName === '')
		{
			$this->sendJsonError(400, 'Missing required parameter: paramName', 'validation.bad_request');
		}

		/** @var SysconfigModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Sysconfig');

		// Treat sensitive keys as if they didn't exist (no information leak).
		if ($model->isSensitiveKey($paramName) || !$model->isKnownKey($paramName))
		{
			$this->sendJsonError(
				404,
				sprintf('Unknown configuration parameter "%s".', $paramName),
				'sysconfig.unknown_param'
			);
		}

		$value = $this->container->appConfig->get($paramName);

		$this->sendJsonResponse([
			$paramName => $value,
		]);
	}
}
