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
 * API handler: GET /v1/sysconfig
 *
 * Returns all NON-SENSITIVE system configuration key-value pairs as a flat object.
 * Sensitive keys (DB password, secret, SMTP credentials, …) are completely omitted —
 * not stubbed — so their existence is not signalled.
 *
 * @since  1.4.0
 */
class GetList extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SysconfigRead);
		$this->requireSuperUser();

		/** @var SysconfigModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Sysconfig');

		$this->sendJsonResponse($model->getNonSensitiveConfig());
	}
}
