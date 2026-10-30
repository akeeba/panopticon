<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\ExtensionInstall;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * The extensioninstall task must re-check, per site, that the initiating user may install there (gh-1089).
 * Task parameters are stored data; whoever wrote them must not be able to reach sites they have no rights on.
 */
class ExtensionInstallAuthorityTest extends AbstractIntegrationTestCase
{
	private const DENIED = 'The initiating user is not allowed to install extensions on this site.';

	private int $groupId;

	private int $adminId;

	private int $superId;

	private int $ownSiteId;

	private int $otherSiteId;

	protected function setUp(): void
	{
		parent::setUp();

		$db = $this->container->db;
		$db->setQuery(
			$db->getQuery(true)
				->insert($db->quoteName('#__groups'))
				->columns([$db->quoteName('title'), $db->quoteName('privileges')])
				->values($db->quote('Site admins') . ', ' . $db->quote('["panopticon.view","panopticon.admin"]'))
		)->execute();
		$this->groupId = (int) $db->insertid();

		$super         = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->superId = (int) $super->getId();
		$this->adminId = (int) $this->createUser(['parameters' => ['usergroups' => [$this->groupId]]])->getId();

		$this->ownSiteId   = $this->makeSite([$this->groupId]);
		$this->otherSiteId = $this->makeSite([]);
	}

	private function makeSite(array $groups): int
	{
		$manager  = $this->container->userManager;
		$property = (new \ReflectionObject($manager))->getProperty('currentUser');
		$previous = $manager->getUser();
		$property->setValue($manager, $manager->getUser($this->superId));

		try
		{
			$suffix = bin2hex(random_bytes(3));

			/** @var Site $site */
			$site = $this->container->mvcFactory->makeTempModel('Site');
			$site->save(
				[
					'name'    => 'Site ' . $suffix,
					'url'     => 'https://test-' . $suffix . '.example/',
					'enabled' => 1,
					'config'  => json_encode(['config' => ['groups' => $groups]]),
				]
			);

			return (int) $site->getId();
		}
		finally
		{
			$property->setValue($manager, $previous);
		}
	}

	/**
	 * Run the task once for the given site and return the recorded result for it.
	 */
	private function runFor(int $siteId, int $initiatingUser): array
	{
		$task         = new \stdClass();
		$task->params = new Registry(['sites' => [$siteId], 'initiating_user' => $initiatingUser]);
		$storage      = new Registry();

		(new ExtensionInstall($this->container))($task, $storage);

		$this->assertSame(1, (int) $storage->get('currentIndex'), 'The site must be marked as processed.');

		return (array) $storage->get('results')[$siteId];
	}

	public function testSiteTheInitiatingUserCannotAdministerIsRefused(): void
	{
		$result = $this->runFor($this->otherSiteId, $this->adminId);

		$this->assertSame('failed', $result['status']);
		$this->assertSame(self::DENIED, $result['message']);
	}

	public function testNonExistentInitiatingUserIsRefused(): void
	{
		$result = $this->runFor($this->ownSiteId, 987654321);

		$this->assertSame(self::DENIED, $result['message']);
	}

	public function testSiteTheInitiatingUserAdministersIsNotRefused(): void
	{
		$result = $this->runFor($this->ownSiteId, $this->adminId);

		$this->assertNotSame(self::DENIED, $result['message']);
	}

	public function testSuperUserIsNotRefused(): void
	{
		$result = $this->runFor($this->otherSiteId, $this->superId);

		$this->assertNotSame(self::DENIED, $result['message']);
	}

	public function testSystemTaskWithoutInitiatingUserIsNotRefused(): void
	{
		$result = $this->runFor($this->otherSiteId, 0);

		$this->assertNotSame(self::DENIED, $result['message']);
	}
}
