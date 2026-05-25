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
 * API handler for POST /v1/site/:id — update an existing site.
 *
 * @since  1.4.0
 */
class Modify extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$user = $this->container->userManager->getUser();

		// Load site (404 if missing). Permission re-checked below to mirror legacy controller.
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		try
		{
			$site->findOrFail($id);
		}
		catch (\Exception)
		{
			$this->sendJsonError(404, 'Site not found.', 'site.not_found');
		}

		if (!$this->canModify($user, $site))
		{
			$this->sendJsonError(403, 'You do not have permission to modify this site.', 'auth.forbidden');
		}

		$body = $this->getJsonBody();

		if (empty($body))
		{
			$this->sendJsonError(400, 'Request body is empty.', 'validation.bad_request');
		}

		try
		{
			$site->applyApiPayload($body, false);
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
			$this->sendJsonError(500, 'Failed to update site: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.update',
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
			[
				'id'      => (int) $site->getId(),
				'name'    => $site->name,
				'url'     => $site->url,
				'enabled' => (bool) $site->enabled,
				'cmsType' => $site->cmsType()->value,
				'notes'   => $site->notes,
				'config'  => $site->getConfig()->toObject(),
			],
			200,
			'Site updated successfully.'
		);
	}

	/**
	 * Check whether the given user can modify the given site. Mirrors `Sites::canAddEditOrSave()`.
	 */
	private function canModify(\Awf\User\User $user, Site $site): bool
	{
		if ($user->getId() <= 0)
		{
			return false;
		}

		if ($user->getPrivilege('panopticon.super'))
		{
			return true;
		}

		$canAdmin   = $user->authorise('panopticon.admin', $site);
		$canEditOwn = $user->authorise('panopticon.editown', $site);
		$myOwnSite  = (int) $user->getId() === (int) $site->created_by;

		if ($canAdmin)
		{
			return true;
		}

		if ($myOwnSite && $canEditOwn)
		{
			return true;
		}

		return false;
	}

}
