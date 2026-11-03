<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Pushsubscriptions;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;

/**
 * Unsubscribing only removes the current user's own subscription; removing an endpoint for everybody stays possible
 * for subscribe() (a browser moving to another user) and for SendWebPush (an expired endpoint) (gh-1096).
 */
class PushsubscriptionsRemoveByEndpointTest extends AbstractIntegrationTestCase
{
	private const ENDPOINT = 'https://push.example.test/push/victim';

	public function testAnotherUserCannotRemoveTheSubscription(): void
	{
		$owner    = (int) $this->createUser()->getId();
		$intruder = (int) $this->createUser()->getId();
		$this->subscribe($owner);

		$this->model()->removeByEndpoint(self::ENDPOINT, $intruder);

		$this->assertCount(1, $this->model()->getSubscriptionsForUser($owner));
	}

	public function testTheOwnerCanRemoveTheSubscription(): void
	{
		$owner = (int) $this->createUser()->getId();
		$this->subscribe($owner);

		$this->model()->removeByEndpoint(self::ENDPOINT, $owner);

		$this->assertCount(0, $this->model()->getSubscriptionsForUser($owner));
	}

	public function testRemovingWithoutAUserRemovesItForEverybody(): void
	{
		$owner = (int) $this->createUser()->getId();
		$this->subscribe($owner);

		$this->model()->removeByEndpoint(self::ENDPOINT);

		$this->assertCount(0, $this->model()->getSubscriptionsForUser($owner));
	}

	private function subscribe(int $userId): void
	{
		$this->model()->save([
			'user_id'    => $userId,
			'endpoint'   => self::ENDPOINT,
			'key_p256dh' => 'BPublicKeyPlaceholder',
			'key_auth'   => 'AuthPlaceholder',
			'encoding'   => 'aes128gcm',
		]);
	}

	private function model(): Pushsubscriptions
	{
		return $this->container->mvcFactory->makeTempModel('Pushsubscriptions');
	}
}
