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
use Akeeba\Panopticon\Library\Queue\QueueInterface;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * API handler for POST /v1/site/:id/extensions/cancel/:extId — remove an extension from the queue.
 *
 * @since  1.4.0
 */
class ExtensionCancelUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$this->requireScope(ApiScope::SitesExtensions);
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'run');
		$user  = $this->container->userManager->getUser();

		if ($extId <= 0)
		{
			$this->sendJsonError(400, 'Invalid extension ID.', 'validation.bad_request');
		}

		$cmsType = $site->cmsType();

		$queuePattern = match ($cmsType)
		{
			CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
			CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
			default            => null,
		};

		if ($queuePattern === null)
		{
			$this->sendJsonError(422, 'Unsupported CMS type for extension updates.', 'site.wrong_cms');
		}

		$queueKey = sprintf($queuePattern, $site->getId());

		try
		{
			/** @var QueueInterface $queue */
			$queue    = $this->container->queueFactory->makeQueue($queueKey);
			$existing = $queue->countByCondition(['data.id' => $extId, 'siteId' => $site->getId()]);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to inspect update queue: ' . $e->getMessage());

			return;
		}

		if ($existing === 0)
		{
			$this->sendJsonError(
				404,
				'Extension is not in the update queue.',
				'task.not_scheduled'
			);
		}

		try
		{
			$queue->clear(['data.id' => $extId, 'siteId' => $site->getId()]);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to cancel extension update: ' . $e->getMessage());

			return;
		}

		AuditLog::record(
			'site.extension.cancelupdate',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value, 'extensionId' => $extId]
		);

		$this->sendJsonResponse(null, 200, 'Extension update cancelled successfully.');
	}
}
