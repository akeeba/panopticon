<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;


use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Task\EnqueueJoomlaUpdateTrait;
use Awf\Mvc\DataController;
use Awf\Text\Text;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

class Coreupdates extends DataController
{
	use EnqueueJoomlaUpdateTrait;

	public function scheduledUpdates()
	{
		$this->csrfProtection();

		// Prepare the return URL before doing anything else
		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		$returnUri = $returnUri ?: $this->getContainer()->router->route('index.php?view=coreupdates');

		// Get the site IDs
		$siteIDs = $this->getSiteIDs();

		// If I do not have any extensions left, redirect with an error
		if (empty($siteIDs))
		{
			$this->setRedirect($returnUri, Text::_('PANOPTICON_COREUPDATES_ERR_INVALID_SELECTION'), 'error');
		}

		// Schedule the requested updates
		$numScheduled = 0;

		foreach ($siteIDs as $siteId)
		{
			/** @var Site $site */
			$site = clone $this->getModel();

			try
			{
				$site->findOrFail($siteId);
			}
			catch (\Exception $e)
			{
				continue;
			}

			if ($site->isJoomlaUpdateTaskScheduled() || $site->isJoomlaUpdateTaskRunning())
			{
				continue;
			}

			$this->enqueueJoomlaUpdate($site, $this->getContainer(), user: $this->container->userManager->getUser());

			$numScheduled++;
		}

		$message = Text::plural('PANOPTICON_COREUPDATES_SCHEDULED_N', $numScheduled);
		$messageType = $numScheduled ? 'success' : 'warning';

		$this->setRedirect($returnUri, $message, $messageType);
	}

	public function cancelUpdates()
	{
		$this->csrfProtection();

		// Prepare the return URL before doing anything else
		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode($returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		$returnUri = $returnUri ?: $this->getContainer()->router->route('index.php?view=coreupdates');

		// Get the site IDs
		$siteIDs = $this->getSiteIDs();

		// If I do not have any extensions left, redirect with an error
		if (empty($siteIDs))
		{
			$this->setRedirect($returnUri, Text::_('PANOPTICON_COREUPDATES_ERR_INVALID_SELECTION'), 'error');
		}

		// Cancel the requested updates
		$numCanceled = 0;

		foreach ($siteIDs as $siteId)
		{
			/** @var Site $site */
			$site = clone $this->getModel();

			try
			{
				$site->findOrFail($siteId);
			}
			catch (\Exception $e)
			{
				continue;
			}

			if (!$site->isJoomlaUpdateTaskScheduled() || $site->isJoomlaUpdateTaskRunning())
			{
				continue;
			}

			/** @var Task|null $task */
			$task = $site->getJoomlaUpdateTask();

			if ($task === null)
			{
				continue;
			}

			if (in_array($task->last_exit_code, [
				Status::WILL_RESUME->value,
				Status::RUNNING->value
			]))
			{
				continue;
			}

			$task->last_exit_code = Status::OK->value;

			$task->unpublish();

			$numCanceled++;
		}

		$message = Text::plural('PANOPTICON_COREUPDATES_CANCELED_N', $numCanceled);
		$messageType = $numCanceled ? 'success' : 'warning';

		$this->setRedirect($returnUri, $message, $messageType);
	}

	protected function getSiteIDs(): array
	{
		$siteIDs = $this->input->get('eid', [], 'array');

		if (!is_array($siteIDs))
		{
			$siteIDs = [];
		}

		$siteIDs = array_map(
			function ($id): ?int {
				if (!is_numeric($id))
				{
					return null;
				}

				$id = intval($id);

				if ($id <= 0)
				{
					return null;
				}

				return $id;
			},
			$siteIDs
		);

		$siteIDs = array_filter($siteIDs);

		return array_unique($siteIDs);
	}
}