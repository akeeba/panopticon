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
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EnqueueJoomlaUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueueWordPressUpdateTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/cmsupdate — schedule a CMS update for the site.
 *
 * DRY: reuses {@see EnqueueJoomlaUpdateTrait::enqueueJoomlaUpdate()} and
 * {@see EnqueueWordPressUpdateTrait::enqueueWordPressUpdate()} which the legacy
 * `Controller\Sites::scheduleJoomlaUpdate()` / `scheduleWordPressUpdate()` use.
 *
 * @since  1.4.0
 */
class CmsUpdate extends AbstractApiHandler
{
	use EnqueueJoomlaUpdateTrait;
	use EnqueueWordPressUpdateTrait;
	use SaveSiteTrait;

	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesCmsUpdate);
		$id   = $this->input->getInt('id', 0);
		$site = $this->getSiteWithPermission($id, 'run');
		$body = $this->getJsonBody();
		$user = $this->container->userManager->getUser();

		$force = (bool) ($body['force'] ?? false);

		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			$this->sendJsonError(422, 'Unsupported CMS type for update scheduling.', 'site.wrong_cms');
		}

		try
		{
			if ($cmsType === CMSType::JOOMLA)
			{
				$this->enqueueJoomlaUpdate($site, $this->container, $force, $user);
			}
			else
			{
				$this->enqueueWordPressUpdate($site, $this->container, $force, $user);
			}

			// Update core.lastAutoUpdateVersion after enqueueing (mirrors legacy controller).
			$this->saveSite(
				$site,
				function (Site $site): void
				{
					$config = $site->getConfig();
					$config->set('core.lastAutoUpdateVersion', $config->get('core.latest.version'));
					$site->config = $config->toString();
				}
			);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to schedule CMS update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.cmsupdate.schedule',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['force' => $force, 'cmsType' => $cmsType->value]
		);

		$this->sendJsonResponse(null, 202, 'CMS update scheduled successfully.');
	}
}
