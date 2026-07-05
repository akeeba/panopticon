<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Queue;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Queue\QueueItem;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Tests for QueueItem — the data-carrying layer of the email/queue stack.
 *
 * A queue item may legitimately carry a NULL siteId: it is the marker for a system-level
 * (siteless) message, such as the extension-install summary email, the self-update-finder
 * notification, or the user-account emails (registration, password reset, …). These tests pin
 * that a QueueItem faithfully carries and round-trips both an integer and a null siteId — the
 * regression in gh-1009 was a downstream consumer that wrongly rejected the null.
 *
 * @since 1.4.0
 */
class QueueItemTest extends AbstractUnitTestCase
{
	public function testGetSiteIdReturnsTheIntegerItWasConstructedWith(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', 123);

		$this->assertSame(123, $item->getSiteId());
	}

	public function testGetSiteIdIsNullByDefault(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail');

		$this->assertNull($item->getSiteId());
	}

	public function testGetSiteIdReturnsNullWhenExplicitlyConstructedWithNull(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', null);

		$this->assertNull($item->getSiteId());
	}

	public function testJsonSerializeEmitsIntegerSiteId(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', 123);

		$serialized = $item->jsonSerialize();

		$this->assertArrayHasKey('siteId', $serialized);
		$this->assertSame(123, $serialized['siteId']);
	}

	public function testJsonSerializeEmitsNullSiteId(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', null);

		$serialized = $item->jsonSerialize();

		$this->assertArrayHasKey('siteId', $serialized);
		$this->assertNull($serialized['siteId']);
	}

	public function testRoundTripPreservesIntegerSiteId(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', 123);

		$restored = QueueItem::fromJson(json_encode($item));

		$this->assertSame(123, $restored->getSiteId());
		$this->assertSame('mail', $restored->getQueueType());
	}

	public function testRoundTripPreservesNullSiteId(): void
	{
		$item = new QueueItem('{"foo":"bar"}', 'mail', null);

		$restored = QueueItem::fromJson(json_encode($item));

		$this->assertNull($restored->getSiteId());
		$this->assertSame('mail', $restored->getQueueType());
	}
}
