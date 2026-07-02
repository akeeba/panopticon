<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Coreupdates;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Coreupdates;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;

/**
 * Regression test for the inverted authorization check in Coreupdates::cancelUpdates(). A stray `!`
 * made the guard let users WITHOUT admin through (and blocked real admins), so any logged-in user
 * could cancel the Joomla core-update task of any site. A non-privileged user must NOT be able to
 * cancel another site's scheduled update; a privileged user must.
 *
 * @since  2.2.0
 */
class CancelUpdatesAuthorityTest extends AbstractControllerIntegrationTestCase
{
	/**
	 * Create a scheduled (not running) Joomla core-update task for the site.
	 */
	private function makeScheduledUpdateTask(int $siteId): Task
	{
		$future = $this->container->dateFactory('now +1 day')->toSql();

		/** @var Task $task */
		$task = $this->container->mvcFactory->makeTempModel('Task');
		$task->save([
			'site_id'         => $siteId,
			'type'            => 'joomlaupdate',
			'params'          => '{}',
			'storage'         => '{}',
			'cron_expression' => '@daily',
			'enabled'         => 1,
			'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
			'next_execution'  => $future,
		]);

		return $task;
	}

	private function taskIsEnabled(int $taskId): bool
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->qn('enabled'))
			->from($db->qn('#__tasks'))
			->where($db->qn('id') . ' = ' . (int) $taskId);

		return (int) $db->setQuery($query)->loadResult() === 1;
	}

	public function testNonAdminCannotCancelUpdatesForSite(): void
	{
		$site = $this->createSite();
		$task = $this->makeScheduledUpdateTask((int) $site->getId());

		// A plain, non-super user with no privileges on the site.
		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$this->dispatch(Coreupdates::class, 'cancelUpdates', ['eid' => [(int) $site->getId()]]);

		// The scheduled task must NOT have been cancelled.
		$this->assertTrue(
			$this->taskIsEnabled((int) $task->getId()),
			'A non-privileged user must not be able to cancel a site\'s scheduled core update.'
		);
	}

	public function testSuperUserCanCancelUpdatesForSite(): void
	{
		$site = $this->createSite();
		$task = $this->makeScheduledUpdateTask((int) $site->getId());

		$super = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $super->getId());

		$this->dispatch(Coreupdates::class, 'cancelUpdates', ['eid' => [(int) $site->getId()]]);

		// The scheduled task must have been cancelled (unpublished).
		$this->assertFalse(
			$this->taskIsEnabled((int) $task->getId()),
			'A super user must be able to cancel a site\'s scheduled core update.'
		);
	}
}
