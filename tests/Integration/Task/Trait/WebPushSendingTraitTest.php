<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueInterface;
use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Task\Trait\WebPushSendingTrait;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;
use Awf\Text\Language;

/**
 * Tests for WebPushSendingTrait::enqueueWebPush() — the producer end of the Web Push stack.
 *
 * Regression coverage for gh-1027: the payload used to be composed once, in whatever language happened
 * to be active when the notification was enqueued, and fanned out to every recipient. It must instead
 * be composed in each recipient's own language, without disturbing the ambient language.
 *
 * The queue factory is replaced with an in-memory one because the real MySQLQueue opens its own DB
 * transaction, which would break the enclosing rollback transaction.
 *
 * @since 1.4.0
 */
class WebPushSendingTraitTest extends AbstractIntegrationTestCase
{
	private const TEMPLATE = 'joomlaupdate_found';

	private mixed $originalQueueFactory = null;

	private object $fakeQueueFactory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->fakeQueueFactory = new class {
			/** @var array<string, QueueInterface> */
			public array $queues = [];

			public function makeQueue(string $queueIdentifier): QueueInterface
			{
				return $this->queues[$queueIdentifier] ??= new class implements QueueInterface {
					public array $items = [];

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
		};

		// The service may already be resolved (frozen) on the shared container; unset before override.
		$this->originalQueueFactory = $this->container->queueFactory;
		unset($this->container['queueFactory']);
		$this->container['queueFactory'] = $this->fakeQueueFactory;
	}

	protected function tearDown(): void
	{
		if ($this->originalQueueFactory !== null)
		{
			unset($this->container['queueFactory']);
			$this->container['queueFactory'] = $this->originalQueueFactory;
			$this->originalQueueFactory      = null;
		}

		parent::tearDown();
	}

	public function testPayloadUsesEachRecipientsOwnLanguage(): void
	{
		$germanId = $this->createSubscribedUser('de-DE');
		$frenchId = $this->createSubscribedUser('fr-FR');

		$this->makeSut()->enqueue($this->makeData(), 123, [$germanId, $frenchId]);

		$titles = $this->payloadTitlesByEndpoint();

		$this->assertSame(
			$this->expectedTitle('de-DE'), $titles['endpoint-' . $germanId],
			'The German subscriber must get the German title.'
		);
		$this->assertSame(
			$this->expectedTitle('fr-FR'), $titles['endpoint-' . $frenchId],
			'The French subscriber must get the French title.'
		);
		$this->assertNotSame(
			$titles['endpoint-' . $germanId], $titles['endpoint-' . $frenchId],
			'Two subscribers with different languages must not receive the same payload.'
		);
	}

	/**
	 * The gh-1027 regression proper.
	 *
	 * A user whose language is "Default" (an empty string) must get the application's default language —
	 * NOT whatever language happened to be active when the notification was enqueued.
	 */
	public function testDefaultLanguageUserGetsAppConfigLanguage(): void
	{
		$this->container->appConfig->set('language', 'de-DE');

		// Simulate an ambient language differing from the app default, e.g. a web CRON request whose
		// caller sent an Accept-Language header, or an acting user with another language.
		$this->withAmbientLanguage('nl-NL', function () {
			$userId = $this->createSubscribedUser('');

			$this->makeSut()->enqueue($this->makeData(), 123, [$userId]);

			$this->assertSame(
				$this->expectedTitle('de-DE'), $this->payloadTitlesByEndpoint()['endpoint-' . $userId],
				'A "Default" language user must get the application default language, not the ambient one.'
			);
		});
	}

	public function testMissingLanguageParameterFallsBack(): void
	{
		$this->container->appConfig->set('language', 'de-DE');

		$this->withAmbientLanguage('nl-NL', function () {
			// No `language` key in the user parameters at all, as opposed to an empty one.
			$userId = $this->createSubscribedUser(null);

			$this->makeSut()->enqueue($this->makeData(), 123, [$userId]);

			$this->assertSame(
				$this->expectedTitle('de-DE'), $this->payloadTitlesByEndpoint()['endpoint-' . $userId],
				'A user with no language parameter must get the application default language.'
			);
		});
	}

	/**
	 * Guards against anyone "simplifying" the fix back into $container->language->loadLanguage(), which
	 * would corrupt every string rendered after us — including the email we are mirroring.
	 */
	public function testGlobalLanguageIsNotMutated(): void
	{
		$this->withAmbientLanguage('nl-NL', function () {
			$before = $this->container->language->text('PANOPTICON_WEBPUSH_TITLE_GENERIC');

			$this->makeSut()->enqueue($this->makeData(), 123, [$this->createSubscribedUser('de-DE')]);

			$this->assertSame(
				'nl-NL', $this->container->language->getLangCode(),
				'Enqueueing a Web Push notification must not change the ambient language code.'
			);
			$this->assertSame(
				$before, $this->container->language->text('PANOPTICON_WEBPUSH_TITLE_GENERIC'),
				'Enqueueing a Web Push notification must not change the ambient language strings.'
			);
		});
	}

	public function testAllSubscriptionsOfOneUserShareOnePayload(): void
	{
		$userId = $this->createSubscribedUser('de-DE');
		$this->createSubscription($userId, 'endpoint-' . $userId . '-second');

		$this->makeSut()->enqueue($this->makeData(), 123, [$userId]);

		$titles = $this->payloadTitlesByEndpoint();

		$this->assertCount(2, $titles, 'Each of the user\'s subscriptions must get its own queue item.');
		$this->assertSame(
			[$this->expectedTitle('de-DE'), $this->expectedTitle('de-DE')], array_values($titles),
			'Every subscription of one user must get the same, correctly translated payload.'
		);
	}

	public function testExcludedTemplateEnqueuesNothing(): void
	{
		$data = $this->makeData();
		$data->set('template', 'action_summary');

		$this->makeSut()->enqueue($data, 123, [$this->createSubscribedUser('de-DE')]);

		$this->assertCount(
			0, $this->pushQueueItems(), 'An excluded template must not enqueue any Web Push notification.'
		);
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Run $callback with the container's language service swapped for one in $langCode, then restore it.
	 */
	private function withAmbientLanguage(string $langCode, callable $callback): void
	{
		$original = $this->container->language;

		unset($this->container['language']);
		$this->container['language'] = $this->container->languageFactory($langCode);

		try
		{
			$callback();
		}
		finally
		{
			unset($this->container['language']);
			$this->container['language'] = $original;
		}
	}

	/**
	 * The title a correctly translated payload is expected to carry for the given language.
	 */
	private function expectedTitle(string $langCode): string
	{
		/** @var Language $language */
		$language = $this->container->languageFactory('en-GB');

		if ($langCode !== 'en-GB')
		{
			$language->loadLanguage($langCode);
		}

		return $language->text('PANOPTICON_WEBPUSH_TITLE_' . strtoupper(self::TEMPLATE));
	}

	/**
	 * Create a user with the given language parameter (null for no parameter at all), plus one push
	 * subscription whose endpoint is `endpoint-<user id>`. Returns the user ID.
	 */
	private function createSubscribedUser(?string $langCode): int
	{
		$user   = $this->createUser($langCode === null ? [] : ['parameters' => ['language' => $langCode]]);
		$userId = (int) $user->getId();

		$this->createSubscription($userId, 'endpoint-' . $userId);

		return $userId;
	}

	private function createSubscription(int $userId, string $endpoint): void
	{
		$db = $this->container->db;

		$db->setQuery(
			$db->getQuery(true)
				->insert($db->quoteName('#__push_subscriptions'))
				->columns([
					$db->quoteName('user_id'), $db->quoteName('endpoint'), $db->quoteName('key_p256dh'),
					$db->quoteName('key_auth'), $db->quoteName('encoding'),
				])
				->values(
					implode(
						',', [
							$userId, $db->quote($endpoint), $db->quote('test-p256dh'), $db->quote('test-auth'),
							$db->quote('aes128gcm'),
						]
					)
				)
		)->execute();
	}

	private function makeData(): Registry
	{
		$data = new Registry();
		$data->set('template', self::TEMPLATE);
		$data->set('email_variables', ['SITE_NAME' => 'Example Site']);

		return $data;
	}

	/**
	 * @return QueueItem[]
	 */
	private function pushQueueItems(): array
	{
		$queue = $this->fakeQueueFactory->makeQueue(QueueTypeEnum::WEBPUSH->value);
		$items = [];

		while (($item = $queue->pop()) !== null)
		{
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * The `title` of each enqueued payload, keyed by the subscription endpoint it was addressed to.
	 *
	 * @return array<string, string>
	 */
	private function payloadTitlesByEndpoint(): array
	{
		$ret = [];

		foreach ($this->pushQueueItems() as $item)
		{
			$itemData = new Registry($item->getData());
			$payload  = json_decode($itemData->get('payload'), true, flags: JSON_THROW_ON_ERROR);

			$ret[$itemData->get('endpoint')] = $payload['title'];
		}

		return $ret;
	}

	/**
	 * An anonymous harness exposing the trait's private enqueueWebPush() as a public pass-through.
	 */
	private function makeSut(): object
	{
		return new class {
			use WebPushSendingTrait;

			public function enqueue(Registry $data, ?int $siteId, array $userIds): void
			{
				$this->enqueueWebPush($data, $siteId, $userIds);
			}
		};
	}
}
