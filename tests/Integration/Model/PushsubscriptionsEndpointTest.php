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
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * A push endpoint comes from the user's browser, and the server later POSTs to it. Only https: URLs are accepted, and
 * the Forbidden IP Ranges policy applies when the subscription is saved, as it does for site URLs.
 */
class PushsubscriptionsEndpointTest extends AbstractIntegrationTestCase
{
	private mixed $originalRanges = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalRanges = $this->container->appConfig->get('forbidden_ip_ranges', []);
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', $this->originalRanges);

		parent::tearDown();
	}

	public static function refusedEndpointProvider(): array
	{
		return [
			'plain http'      => ['http://push.example.com/send/abc'],
			'no scheme'       => ['push.example.com/send/abc'],
			'other scheme'    => ['ftp://push.example.com/send/abc'],
			'not a URL'       => ['not a url'],
			'https, no host'  => ['https:///send/abc'],
		];
	}

	#[DataProvider('refusedEndpointProvider')]
	public function testEndpointsWhichAreNotHttpsUrlsAreRefused(string $endpoint): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', []);

		$this->expectException(RuntimeException::class);

		$this->makeSubscription($endpoint)->check();
	}

	public function testEndpointOnAForbiddenHostIsRefused(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', ['127.0.0.0/8']);

		$this->expectException(RuntimeException::class);

		$this->makeSubscription('https://127.0.0.1/push/abc')->check();
	}

	public function testLoopbackIsAllowedWhenThePolicyIsDisabled(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', []);

		$subscription = $this->makeSubscription('https://127.0.0.1/push/abc');
		$subscription->check();

		$this->assertSame('https://127.0.0.1/push/abc', $subscription->endpoint);
	}

	public function testAPushServiceEndpointIsAccepted(): void
	{
		// An IP literal outside the forbidden range: no DNS lookup is involved.
		$this->container->appConfig->set('forbidden_ip_ranges', ['127.0.0.0/8']);

		$subscription = $this->makeSubscription('https://203.0.113.10/fcm/send/abc');
		$subscription->check();

		$this->assertSame('https://203.0.113.10/fcm/send/abc', $subscription->endpoint);
	}

	private function makeSubscription(string $endpoint): Pushsubscriptions
	{
		/** @var Pushsubscriptions $model */
		$model = $this->container->mvcFactory->makeTempModel('Pushsubscriptions');
		$model->bind([
			'user_id'    => $this->createUser()->getId(),
			'endpoint'   => $endpoint,
			'key_p256dh' => 'BPublicKeyPlaceholder',
			'key_auth'   => 'AuthPlaceholder',
			'encoding'   => 'aes128gcm',
		]);

		return $model;
	}
}
