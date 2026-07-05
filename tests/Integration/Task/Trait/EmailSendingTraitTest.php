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
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * Tests for EmailSendingTrait::enqueueEmail() — the producer end of the email stack.
 *
 * Regression coverage for gh-1009: enqueueEmail() accepts a nullable siteId on purpose (system
 * vs. site-scoped emails). These tests assert that both a null and an integer siteId make it onto
 * the mail queue intact.
 *
 * The queue factory is replaced with an in-memory one because the real MySQLQueue opens its own DB
 * transaction, which would break the enclosing rollback transaction. immediate_email is disabled so
 * the SendMail task is not run as a side effect; an excluded template keeps the WebPush path quiet.
 *
 * @since 1.4.0
 */
class EmailSendingTraitTest extends AbstractIntegrationTestCase
{
	private mixed $originalQueueFactory = null;

	private object $fakeQueueFactory;

	protected function setUp(): void
	{
		parent::setUp();

		// Do not fire the SendMail task as a side effect of enqueueing.
		$this->container->appConfig->set('immediate_email', 0);

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

	public function testEnqueueEmailAcceptsNullSiteId(): void
	{
		$this->makeSut()->enqueue($this->makeData(), null);

		$item = $this->poppedMailItem();

		$this->assertInstanceOf(QueueItem::class, $item);
		$this->assertNull($item->getSiteId(), 'A system-level email must keep its null siteId on the queue.');
	}

	public function testEnqueueEmailAcceptsIntegerSiteId(): void
	{
		$this->makeSut()->enqueue($this->makeData(), 777);

		$item = $this->poppedMailItem();

		$this->assertInstanceOf(QueueItem::class, $item);
		$this->assertSame(777, $item->getSiteId(), 'A site-scoped email must keep its integer siteId on the queue.');
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function makeData(): Registry
	{
		$data = new Registry();
		// An excluded template keeps enqueueWebPush() from touching the queue.
		$data->set('template', 'action_summary');
		$data->set('email_variables', ['FOO' => 'bar']);

		return $data;
	}

	private function poppedMailItem(): ?QueueItem
	{
		return $this->fakeQueueFactory->makeQueue(QueueTypeEnum::MAIL->value)->pop();
	}

	/**
	 * An anonymous harness exposing the trait's private enqueueEmail() as a public pass-through.
	 */
	private function makeSut(): object
	{
		return new class {
			use EmailSendingTrait;

			public function enqueue(Registry $data, ?int $siteId): void
			{
				$this->enqueueEmail($data, $siteId);
			}
		};
	}
}
