<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueInterface;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Task\SendWebPush;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;

/**
 * Push notifications go out through the HTTP factory's client, so they obey the Forbidden IP Ranges policy like every
 * other outbound request (GHSA-6234-p3mh-7j3x). The push endpoint is supplied by the user's browser.
 */
class SendWebPushTest extends AbstractIntegrationTestCase
{
	private mixed $originalRanges = null;

	private mixed $originalQueueFactory = null;

	private mixed $originalHttpFactory = null;

	private mixed $originalMailFrom = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalRanges   = $this->container->appConfig->get('forbidden_ip_ranges', []);
		$this->originalMailFrom = $this->container->appConfig->get('mailfrom', '');
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', $this->originalRanges);
		$this->container->appConfig->set('mailfrom', $this->originalMailFrom);

		if ($this->originalHttpFactory !== null)
		{
			unset($this->container['httpFactory']);
			$this->container['httpFactory'] = $this->originalHttpFactory;
			$this->originalHttpFactory      = null;
		}

		if ($this->originalQueueFactory !== null)
		{
			unset($this->container['queueFactory']);
			$this->container['queueFactory'] = $this->originalQueueFactory;
			$this->originalQueueFactory      = null;
		}

		parent::tearDown();
	}

	public function testPushToAForbiddenHostIsRefusedByTheGuard(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', ['127.0.0.0/8']);

		// Port 9 (discard) is closed: an unguarded client gets "connection refused" instead of the guard's refusal.
		$this->seedQueue(['https://127.0.0.1:9/push/abc' => 'aes128gcm']);

		$log  = new \ArrayObject();
		$task = new SendWebPush($this->container);
		$task->setLogger($this->makeSpyLogger($log));
		$task->__invoke((object) [], new Registry());

		$refusals = array_filter(
			$log->getArrayCopy(),
			fn(array $record) => str_contains($record['message'], 'The request target is not allowed on this installation.')
		);

		$this->assertNotEmpty(
			$refusals,
			"The push request did not go through the Forbidden IP Ranges guard. Log:\n"
			. implode("\n", array_column($log->getArrayCopy(), 'message'))
		);
	}

	/**
	 * The VAPID contact is the configured "mail from" address, not a placeholder (gh-1094).
	 */
	public function testVapidSubjectIsTheConfiguredMailFromAddress(): void
	{
		$this->container->appConfig->set('mailfrom', 'monitoring@example.test');
		$this->seedQueue(['https://push.example.test/push/abc' => 'aes128gcm']);
		$requests = $this->captureRequests();

		(new SendWebPush($this->container))->__invoke((object) [], new Registry());

		$this->assertCount(1, $requests);
		$this->assertSame('mailto:monitoring@example.test', $this->vapidClaims($requests[0])['sub'] ?? null);
	}

	/**
	 * A queue item without an encoding is sent with aes128gcm, the one every browser must support (gh-1095).
	 */
	public function testItemWithoutEncodingIsSentAsAes128gcm(): void
	{
		$this->seedQueue(['https://push.example.test/push/abc' => null]);
		$requests = $this->captureRequests();

		(new SendWebPush($this->container))->__invoke((object) [], new Registry());

		$this->assertCount(1, $requests);
		$this->assertSame('aes128gcm', $requests[0]->getHeaderLine('Content-Encoding'));
	}

	/**
	 * A subscription with an unknown encoding is a failed send, not the end of the run (gh-1095).
	 */
	public function testUnknownEncodingDoesNotAbortTheRun(): void
	{
		$this->seedQueue(
			[
				'https://push.example.test/push/bad'  => 'bogus',
				'https://push.example.test/push/good' => 'aes128gcm',
			]
		);
		$requests = $this->captureRequests();

		(new SendWebPush($this->container))->__invoke((object) [], new Registry());

		$this->assertCount(1, $requests);
		$this->assertSame('/push/good', $requests[0]->getUri()->getPath());
	}

	/**
	 * Swap the HTTP factory for one whose client records every request and answers 201, without any network access.
	 *
	 * @return  \ArrayObject<int, RequestInterface>
	 */
	private function captureRequests(): \ArrayObject
	{
		$requests = new \ArrayObject();

		$this->originalHttpFactory = $this->container->httpFactory;
		unset($this->container['httpFactory']);
		$this->container['httpFactory'] = new class($requests) {
			public function __construct(private readonly \ArrayObject $requests) {}

			public function makeClient(...$args): Client
			{
				$requests = $this->requests;

				return new Client(
					[
						'handler' => (function () use ($requests) {
							$stack = HandlerStack::create(new MockHandler(array_fill(0, 10, new Response(201))));
							$stack->push(
								Middleware::mapRequest(
									function (RequestInterface $request) use ($requests) {
										$requests[] = $request;

										return $request;
									}
								)
							);

							return $stack;
						})(),
					]
				);
			}
		};

		return $requests;
	}

	/**
	 * The claims of the VAPID JWT in a push request's Authorization header (either scheme).
	 */
	private function vapidClaims(RequestInterface $request): array
	{
		$authorization = $request->getHeaderLine('Authorization');

		$found = preg_match('/^(?:vapid t=|WebPush )([\w-]+\.[\w-]+\.[\w-]+)/', $authorization, $m);

		$this->assertSame(1, $found, $authorization);

		$payload = explode('.', $m[1])[1];

		return json_decode(base64_decode(strtr($payload, '-_', '+/')), true) ?: [];
	}

	/**
	 * Replace the push queue with an in-memory one holding one item per endpoint.
	 *
	 * @param   array<string, string|null>  $items  endpoint => content encoding; null leaves the encoding out
	 */
	private function seedQueue(array $items): void
	{
		$b64   = fn(string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
		$queue = $this->makeInMemoryQueue();

		foreach ($items as $endpoint => $encoding)
		{
			$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
			$ec  = openssl_pkey_get_details($key)['ec'];

			$itemData = new Registry();
			$itemData->set('endpoint', $endpoint);
			$itemData->set(
				'key_p256dh',
				$b64("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))
			);
			$itemData->set('key_auth', $b64(random_bytes(16)));

			if ($encoding !== null)
			{
				$itemData->set('encoding', $encoding);
			}

			$itemData->set('payload', '{"title":"Test"}');
			$itemData->set('retries', 0);

			$queue->push(new QueueItem($itemData->toString(), QueueTypeEnum::WEBPUSH->value, null));
		}

		$this->originalQueueFactory = $this->container->queueFactory;
		unset($this->container['queueFactory']);
		$this->container['queueFactory'] = new class($queue) {
			public function __construct(private readonly QueueInterface $queue) {}

			public function makeQueue(string $queueIdentifier): QueueInterface
			{
				return $this->queue;
			}
		};
	}

	private function makeSpyLogger(\ArrayObject $log): AbstractLogger
	{
		return new class($log) extends AbstractLogger {
			public function __construct(private readonly \ArrayObject $log) {}

			public function log($level, string|\Stringable $message, array $context = []): void
			{
				$this->log[] = ['level' => $level, 'message' => (string) $message];
			}
		};
	}

	private function makeInMemoryQueue(): QueueInterface
	{
		return new class implements QueueInterface {
			private array $items = [];

			public function push(QueueItem $item, \DateTime|int|string|null $time = null): void
			{
				$this->items[] = $item;
			}

			public function pop(): ?QueueItem
			{
				return array_shift($this->items);
			}

			public function clear(array $conditions = []): void
			{
				$this->items = [];
			}

			public function countByCondition(array $conditions = []): int
			{
				return count($this->items);
			}

			public function count(): int
			{
				return count($this->items);
			}
		};
	}
}
