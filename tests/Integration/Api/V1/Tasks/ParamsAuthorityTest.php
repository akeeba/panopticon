<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Tasks;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Task\Add;
use Akeeba\Panopticon\Controller\Api\V1\Task\Modify;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * A non-super user who administers one site must not be able to point a task's `params.sites` at other sites, nor
 * impersonate another user through `params.initiating_user` (gh-1089 follow-up).
 */
class ParamsAuthorityTest extends AbstractApiIntegrationTestCase
{
	private int $groupId;

	private int $ownSiteId;

	private int $otherSiteId;

	private int $adminId;

	private int $otherUserId;

	protected function setUp(): void
	{
		parent::setUp();

		$db = $this->container->db;
		$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__tasks')))->execute();

		$this->container->taskRegistry->add('test_authority', new class implements CallbackInterface
		{
			public function __invoke(object $task, Registry $storage): int
			{
				return Status::OK->value;
			}

			public function getTaskType(): string
			{
				return 'test_authority';
			}

			public function getDescription(): string
			{
				return 'Test params authority callback';
			}
		});

		$db->setQuery(
			$db->getQuery(true)
				->insert($db->quoteName('#__groups'))
				->columns([$db->quoteName('title'), $db->quoteName('privileges')])
				->values($db->quote('Site admins') . ', ' . $db->quote('["panopticon.view","panopticon.admin"]'))
		)->execute();
		$this->groupId = (int) $db->insertid();

		$super         = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->adminId = (int) $this->createUser(['parameters' => ['usergroups' => [$this->groupId]]])->getId();

		$this->ownSiteId   = $this->makeSite((int) $super->getId(), [$this->groupId]);
		$this->otherSiteId = $this->makeSite((int) $super->getId(), []);
		$this->otherUserId = (int) $super->getId();
	}

	private function makeSite(int $actorId, array $groups): int
	{
		$manager  = $this->container->userManager;
		$property = (new \ReflectionObject($manager))->getProperty('currentUser');
		$previous = $manager->getUser();
		$property->setValue($manager, $manager->getUser($actorId));

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

	private function storedParams(int $taskId): array
	{
		$db  = $this->container->db;
		$raw = $db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('params'))
				->from($db->quoteName('#__tasks'))
				->where($db->quoteName('id') . ' = ' . $taskId)
		)->loadResult();

		return json_decode((string) $raw, true) ?: [];
	}

	private function add(array $params): array
	{
		$this->loginAs($this->adminId);
		$this->setJsonRequestBody(
			[
				'site_id'         => $this->ownSiteId,
				'type'            => 'test_authority',
				'cron_expression' => '@daily',
				'params'          => $params,
			]
		);

		return $this->invokeHandler(Add::class);
	}

	public function testAddRejectsSitesTheUserDoesNotAdminister(): void
	{
		$response = $this->add(['sites' => [$this->ownSiteId, $this->otherSiteId]]);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testAddRejectsSitesGivenAsJsonString(): void
	{
		$response = $this->add(['sites' => json_encode([$this->otherSiteId])]);

		$this->assertSame(403, $response['status']);
	}

	public function testAddAcceptsOwnSitesAndForcesInitiatingUser(): void
	{
		$response = $this->add(['sites' => [$this->ownSiteId], 'initiating_user' => $this->otherUserId]);

		$this->assertSame(201, $response['status']);

		$stored = $this->storedParams((int) $response['body']['data']['id']);
		$this->assertEquals([$this->ownSiteId], $stored['sites']);
		$this->assertSame($this->adminId, (int) $stored['initiating_user']);
	}

	public function testSuperUserMayTargetAnySiteAndUser(): void
	{
		$this->loginAs($this->otherUserId);
		$this->setJsonRequestBody(
			[
				'site_id'         => $this->ownSiteId,
				'type'            => 'test_authority',
				'cron_expression' => '@daily',
				'params'          => ['sites' => [$this->otherSiteId], 'initiating_user' => $this->adminId],
			]
		);

		$response = $this->invokeHandler(Add::class);

		$this->assertSame(201, $response['status']);
		$stored = $this->storedParams((int) $response['body']['data']['id']);
		$this->assertEquals([$this->otherSiteId], $stored['sites']);
		$this->assertSame($this->adminId, (int) $stored['initiating_user']);
	}

	public function testModifyRejectsSitesTheUserDoesNotAdminister(): void
	{
		$created = $this->add(['sites' => [$this->ownSiteId]]);
		$id      = (int) $created['body']['data']['id'];

		$this->setJsonRequestBody(['params' => ['sites' => [$this->otherSiteId]]]);
		$response = $this->invokeHandler(Modify::class, ['id' => $id]);

		$this->assertSame(403, $response['status']);
		$this->assertEquals([$this->ownSiteId], $this->storedParams($id)['sites']);
	}

	public function testModifyForcesInitiatingUser(): void
	{
		$created = $this->add(['sites' => [$this->ownSiteId]]);
		$id      = (int) $created['body']['data']['id'];

		$this->setJsonRequestBody(
			['params' => ['sites' => [$this->ownSiteId], 'initiating_user' => $this->otherUserId]]
		);
		$response = $this->invokeHandler(Modify::class, ['id' => $id]);

		$this->assertSame(200, $response['status']);
		$this->assertSame($this->adminId, (int) $this->storedParams($id)['initiating_user']);
	}
}
