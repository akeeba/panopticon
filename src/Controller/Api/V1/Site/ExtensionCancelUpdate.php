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
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;

/**
 * API handler for POST /v1/site/:id/extensions/cancel/:extId — remove an extension from the update queue.
 *
 * @since  1.4.0
 */
class ExtensionCancelUpdate extends AbstractApiHandler
{
	public function handle(): void
	{
		$id    = $this->input->getInt('id', 0);
		$extId = $this->input->getInt('extId', 0);
		$site  = $this->getSiteWithPermission($id, 'run');

		if ($extId <= 0)
		{
			$this->sendJsonError(400, 'Invalid extension ID.');
		}

		try
		{
			$queuePattern = match ($site->cmsType())
			{
				CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
				CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
				default            => null,
			};

			if ($queuePattern === null)
			{
				$this->sendJsonError(400, 'Unsupported CMS type for extension updates.');
			}

			$queueKey = sprintf($queuePattern, $site->getId());
			$queue    = $this->container->queueFactory->makeQueue($queueKey);

			// Remove the specific extension from the queue
			$queue->clear(['data.id' => $extId, 'siteId' => $site->getId()]);
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to cancel extension update: ' . $e->getMessage());
		}

		$this->sendJsonResponse(null, 200, 'Extension update cancelled successfully.');
	}
}
