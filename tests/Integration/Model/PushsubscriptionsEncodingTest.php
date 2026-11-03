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
 * Push subscriptions store a content encoding the sender can use: aes128gcm (RFC 8291) by default, or the legacy
 * aesgcm when the browser asked for it. Anything else is refused when the subscription is saved (gh-1095).
 */
class PushsubscriptionsEncodingTest extends AbstractIntegrationTestCase
{
	public static function defaultEncodingProvider(): array
	{
		return [
			'empty string' => [''],
			'null'         => [null],
		];
	}

	#[DataProvider('defaultEncodingProvider')]
	public function testMissingEncodingDefaultsToAes128gcm(?string $encoding): void
	{
		$subscription = $this->makeSubscription($encoding);
		$subscription->check();

		$this->assertSame('aes128gcm', $subscription->encoding);
	}

	public static function supportedEncodingProvider(): array
	{
		return [['aes128gcm'], ['aesgcm']];
	}

	#[DataProvider('supportedEncodingProvider')]
	public function testSupportedEncodingIsKept(string $encoding): void
	{
		$subscription = $this->makeSubscription($encoding);
		$subscription->check();

		$this->assertSame($encoding, $subscription->encoding);
	}

	public static function unknownEncodingProvider(): array
	{
		return [['bogus'], ['AES128GCM'], ['aes256gcm']];
	}

	#[DataProvider('unknownEncodingProvider')]
	public function testUnknownEncodingIsRefused(string $encoding): void
	{
		$this->expectException(RuntimeException::class);

		$this->makeSubscription($encoding)->check();
	}

	private function makeSubscription(?string $encoding): Pushsubscriptions
	{
		/** @var Pushsubscriptions $model */
		$model = $this->container->mvcFactory->makeTempModel('Pushsubscriptions');
		$model->bind([
			'user_id'    => $this->createUser()->getId(),
			'endpoint'   => 'https://push.example.test/push/abc',
			'key_p256dh' => 'BPublicKeyPlaceholder',
			'key_auth'   => 'AuthPlaceholder',
			'encoding'   => $encoding,
		]);

		return $model;
	}
}
