<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Selfupdate;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Awf\Registry\Registry;
use Awf\User\User;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

#[AsTask(
	name: 'selfupdatefinder',
	description: 'PANOPTICON_TASKTYPE_SELFUPDATEFINDER'
)]
class SelfUpdateFinder extends AbstractCallback
{
	use EmailSendingTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$this->logger->info('Checking for Panopticon updates');

		/** @var Selfupdate $model */
		$model = $this->container->mvcFactory->makeTempModel('Selfupdate');

		if (!$model->hasUpdate())
		{
			$this->logger->debug('No updates are available.');

			return Status::OK->value;
		}

		try
		{
			$updateInfo = $model->getUpdateInformation();
		}
		catch (\Throwable)
		{
			$this->logger->debug('Could not retrieve update information.');

			return Status::OK->value;
		}

		if ($updateInfo->stuck)
		{
			$this->logger->notice('Panopticon updates are stuck.');

			return Status::OK->value;
		}

		if (!$updateInfo->loadedUpdate)
		{
			$this->logger->notice('Could not load Panopticon updates');

			return Status::OK->value;
		}

		$latestVersion = $model->getLatestVersion();

		if (empty($latestVersion))
		{
			$this->logger->notice('Could not find the latest Panopticon version');

			return Status::OK->value;
		}

		// Check if this is the same as the last seen version. If so, no action will be taken.
		$lastSeenVersion = $this->getLastSeenVersion();

		if ($lastSeenVersion === $latestVersion->version)
		{
			$this->logger->debug('An email about this version has already been sent.');

			return Status::OK->value;
		}

		// Schedule a mail for all Super Users.
		$this->logger->info(sprintf('Notifying Super Users about the new Panopticon version %s', $latestVersion->version));

		$data      = (new Registry())
			->loadArray(
				[
					'template'        => 'selfupdate_found',
					'email_variables' => [
						'NEW_VERSION' => $latestVersion->version,
						'OLD_VERSION' => defined('AKEEBA_PANOPTICON_VERSION')
							? AKEEBA_PANOPTICON_VERSION : 'dev',
					],
					'permissions'     => ['panopticon.super'],
				]
			);

		$this->enqueueEmail($data, null, 'now');

		// Update the last seen version.
		$this->setLastSeenVersion($latestVersion->version);

		return Status::OK->value;
	}

	private function getLastSeenVersion(): ?string
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('selfupdate.lastSeen'));

		try
		{
			return $db->setQuery($query)->loadResult() ?: null;
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	private function setLastSeenVersion(string $version): void
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->delete($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('selfupdate.lastSeen'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			return;
		}

		$o = (object) [
			'key'   => 'selfupdate.lastSeen',
			'value' => $version,
		];

		$db->insertObject('#__akeeba_common', $o);
	}

	private function getSuperUsers(): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true);
		$query->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($query->jsonExtract($db->quoteName('parameters'), '$.acl.panopticon.super') . ' = TRUE');

		try
		{
			$ids = $db->setQuery($query)->loadColumn();
		}
		catch (\Exception $e)
		{
			$ids = [];
		}

		return array_filter(
			array_map(
				fn($id) => $this->container->userManager->getUser($id),
				$ids
			),
			fn(?User $user) => ($user instanceof User) && $user->getId() > 0
		);
	}
}