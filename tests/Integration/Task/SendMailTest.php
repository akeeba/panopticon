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
use Akeeba\Panopticon\Task\SendMail;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use ArrayObject;
use Awf\Registry\Registry;
use Awf\User\User;

/**
 * Tests for the SendMail task — the consumer end of the email stack.
 *
 * Regression coverage for gh-1009: a NULL siteId is the legitimate marker for a system-level
 * (siteless) email (extension-install summary, self-update finder, account emails). The private
 * helper SendMail::sendLanguageBatches() had typed its $siteId parameter as a non-nullable int,
 * so every siteless email threw a TypeError instead of being sent. These tests exercise the full
 * SendMail::__invoke() flow and the helper directly with BOTH an integer and a null siteId.
 *
 * The two external effects are neutralised so the tests are deterministic and send no real email:
 *   - the mailer service is replaced with a spy that records sends instead of dispatching them;
 *   - the queue factory is replaced with an in-memory queue (the real MySQLQueue issues its own
 *     COMMIT, which would break the test's enclosing rollback transaction).
 * The Site model lookup is side-stepped by driving the recipient_id code path.
 *
 * @since 1.4.0
 */
class SendMailTest extends AbstractIntegrationTestCase
{
	private mixed $originalMailer = null;

	private mixed $originalQueueFactory = null;

	private ArrayObject $mailLog;

	protected function setUp(): void
	{
		parent::setUp();

		$this->mailLog = new ArrayObject();

		// Never dispatch real email: swap the mailer for a recording spy. The service may already be
		// resolved (frozen) on the shared container, so unset it before overriding.
		$this->originalMailer = $this->container->mailer;
		unset($this->container['mailer']);
		$this->container['mailer'] = $this->makeSpyMailer($this->mailLog);
	}

	protected function tearDown(): void
	{
		// Restore the real services on the shared container singleton.
		if ($this->originalMailer !== null)
		{
			unset($this->container['mailer']);
			$this->container['mailer'] = $this->originalMailer;
			$this->originalMailer      = null;
		}

		if ($this->originalQueueFactory !== null)
		{
			unset($this->container['queueFactory']);
			$this->container['queueFactory'] = $this->originalQueueFactory;
			$this->originalQueueFactory      = null;
		}

		parent::tearDown();
	}

	public function testInvokeSendsSystemLevelEmailWithNullSiteId(): void
	{
		$user = $this->createUser();

		$this->seedMailQueue([[$this->makeMailItemData($user), null]]);

		$task = new SendMail($this->container);

		// Before the fix this throws:
		// "sendLanguageBatches(): Argument #7 ($siteId) must be of type int, null given".
		$task->__invoke((object) [], new Registry());

		$this->assertCount(1, $this->mailLog, 'The system-level (null siteId) email must be sent.');
		$this->assertSame($user->getEmail(), $this->mailLog[0]['to']);
	}

	public function testInvokeSendsSiteScopedEmailWithIntegerSiteId(): void
	{
		$user = $this->createUser();

		$this->seedMailQueue([[$this->makeMailItemData($user), 4242]]);

		$task = new SendMail($this->container);

		$task->__invoke((object) [], new Registry());

		$this->assertCount(1, $this->mailLog, 'The site-scoped (integer siteId) email must be sent.');
		$this->assertSame($user->getEmail(), $this->mailLog[0]['to']);
	}

	/**
	 * Pin the specific method whose signature regressed: it must accept a null siteId and render
	 * it as "site 0" in its log messages, exactly as the pre-refactor inline code did.
	 */
	public function testSendLanguageBatchesAcceptsNullSiteId(): void
	{
		$task       = new SendMail($this->container);
		$recipients = ['en-GB' => [['recipient@example.test', 'Recipient']]];

		$this->invokeSendLanguageBatches($task, $recipients, null);

		$this->assertCount(1, $this->mailLog);
	}

	public function testSendLanguageBatchesAcceptsIntegerSiteId(): void
	{
		$task       = new SendMail($this->container);
		$recipients = ['en-GB' => [['recipient@example.test', 'Recipient']]];

		$this->invokeSendLanguageBatches($task, $recipients, 123);

		$this->assertCount(1, $this->mailLog);
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Build the JSON data for a mail-queue item addressed to a specific user (recipient_id path),
	 * which keeps recipient resolution deterministic and independent of the Site model.
	 */
	private function makeMailItemData(User $user): string
	{
		$data = new Registry();
		$data->set('template', 'test_template');
		$data->set('language', 'en-GB');
		$data->set('recipient_id', $user->getId());
		$data->set('email_variables', ['FOO' => 'bar']);

		return $data->toString();
	}

	/**
	 * Replace the container's queue factory with an in-memory one seeded with mail items.
	 *
	 * @param   array<array{0: string, 1: int|null}>  $items  [ [itemDataJson, siteId], ... ]
	 */
	private function seedMailQueue(array $items): void
	{
		$queue = $this->makeInMemoryQueue();

		foreach ($items as [$itemData, $siteId])
		{
			$queue->push(new QueueItem($itemData, QueueTypeEnum::MAIL->value, $siteId));
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

	/**
	 * Call the private SendMail::sendLanguageBatches() with the minimum arguments, varying siteId.
	 */
	private function invokeSendLanguageBatches(SendMail $task, array $recipients, ?int $siteId): void
	{
		$method = new \ReflectionMethod($task, 'sendLanguageBatches');
		$method->setAccessible(true);
		$method->invoke($task, $recipients, 'test_template', 'en-GB', [], [], null, $siteId);
	}

	/**
	 * A spy mailer: duck-typed against the methods SendMail uses. It records each Send() into the
	 * shared log instead of dispatching, and stays clone-safe (SendMail clones the mailer per
	 * language batch) because the log is a shared ArrayObject.
	 */
	private function makeSpyMailer(ArrayObject $log): object
	{
		return new class($log) {
			public string $Body = '';

			private array $recipients = [];

			public function __construct(public ArrayObject $log) {}

			public function initialiseWithTemplate(string $type, string $language = 'en-GB', array $replacements = []): void
			{
				// Pretend the template exists so a send actually happens.
				$this->Body       = 'Rendered body for ' . $type . ' (' . $language . ')';
				$this->recipients = [];
			}

			public function addRecipient($recipient, $name = ''): void
			{
				$this->recipients[] = $recipient;
			}

			public function addBCC($bcc, $name = ''): void
			{
				$this->recipients[] = $bcc;
			}

			public function addAttachment(
				$attachment, $name = '', $encoding = 'base64', $type = 'application/octet-stream', $disposition = 'attachment'
			): void
			{
				// no-op
			}

			public function Send(): bool
			{
				$this->log[] = [
					'to'   => $this->recipients[0] ?? null,
					'body' => $this->Body,
				];

				return true;
			}
		};
	}

	/**
	 * A minimal in-memory queue implementing QueueInterface. Avoids the real MySQLQueue, whose
	 * push()/pop() open their own DB transactions and would break the test's rollback isolation.
	 */
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
