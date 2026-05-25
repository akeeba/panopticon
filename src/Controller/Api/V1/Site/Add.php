<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Site;
use RuntimeException;

/**
 * API handler for PUT /v1/site — create a new site.
 *
 * @since  1.4.0
 */
class Add extends AbstractApiHandler
{
	public function handle(): void
	{
		$user = $this->container->userManager->getUser();

		// Mirror legacy `Controller\Sites::onBeforeAdd()` ACL: super (implied) OR admin OR addown.
		if (
			!$user->getPrivilege('panopticon.super')
			&& !$user->getPrivilege('panopticon.admin')
			&& !$user->getPrivilege('panopticon.addown')
		)
		{
			$this->sendJsonError(403, 'You do not have permission to add sites.', 'auth.forbidden');
		}

		$body = $this->getJsonBody();

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		try
		{
			$site->applyApiPayload($body, true);
		}
		catch (RuntimeException $e)
		{
			if ($e->getCode() === 400)
			{
				$this->sendJsonError(400, $e->getMessage(), 'validation.bad_request');
			}

			$this->sendJsonError(422, $e->getMessage(), 'validation.unprocessable');
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to create site: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.create',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			[
				'name' => $site->name,
				'url'  => $site->url,
			]
		);

		$this->sendJsonResponse(
			$this->serialiseSite($site),
			201,
			'Site created successfully.'
		);
	}

	private function serialiseSite(Site $site): array
	{
		return [
			'id'      => (int) $site->getId(),
			'name'    => $site->name,
			'url'     => $site->url,
			'enabled' => (bool) $site->enabled,
			'cmsType' => $site->cmsType()->value,
			'notes'   => $site->notes,
			'config'  => $site->getConfig()->toObject(),
		];
	}

}
