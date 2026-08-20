<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Logger;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Pure-unit tests for {@see ForkedLogger}.
 *
 * The end-to-end behaviour of the per-task `try { pushLogger(...) } finally { popLogger(...) }`
 * pattern cannot be exercised without a real long-lived daemon with simulated sites and real
 * log file handles. The unit tests below cover the structural primitives the per-task code
 * relies on (push stacking order, pop removal by identity, dedup-on-push, clear-on-clear) and
 * the bug fix specific to gh-1060 cause 6 (pop removes the matching instance even after
 * `hasLogger()` deduplication). Per-task migration is verified by code review (one
 * `try/finally` pair per file, symmetric to the existing patterns) plus the `grep` count
 * match in the verification step.
 *
 * @since  2.3.2
 */
class ForkedLoggerTest extends AbstractUnitTestCase
{
	/**
	 * Build a recording logger that captures every log call into an array indexed by level.
	 *
	 * @param   string  $tag  A label embedded in the captured messages so we can tell instances apart.
	 */
	private function makeRecorder(string $tag): LoggerInterface
	{
		return new class($tag) extends AbstractLogger {
			private array $messages = [];

			public function __construct(private readonly string $tag)
			{
			}

			public function log($level, $message, array $context = []): void
			{
				$line = is_string($message) ? $message : ($context['message'] ?? '');
				$this->messages[] = sprintf('[%s][%s] %s', $this->tag, (string) $level, $line);
			}

			/** @return string[] */
			public function getMessages(): array
			{
				return $this->messages;
			}
		};
	}

	/**
	 * Reflectively read the private $loggers array.
	 *
	 * @return  LoggerInterface[]
	 */
	private function getLoggers(ForkedLogger $logger): array
	{
		$ref = new \ReflectionClass(ForkedLogger::class);
		$prop = $ref->getProperty('loggers');
		$prop->setAccessible(true);

		return $prop->getValue($logger);
	}

	public function testPopLoggerRemovesTheMatchingLogger(): void
	{
		$first  = $this->makeRecorder('first');
		$second = $this->makeRecorder('second');

		$sut = new ForkedLogger();
		$sut->pushLogger($first);
		$sut->pushLogger($second);

		// Sanity check: both are wired up.
		$sut->log('info', 'ping');
		$this->assertCount(1, $first->getMessages());
		$this->assertCount(1, $second->getMessages());

		$this->assertTrue($sut->popLogger($first));

		// After popping the first, only the second should receive messages.
		$sut->log('info', 'pong');

		$this->assertCount(1, $first->getMessages(), 'Popped logger must not receive further messages');
		$this->assertCount(2, $second->getMessages(), 'Remaining logger must keep receiving messages');

		// And the internal stack should be re-indexed.
		$remaining = $this->getLoggers($sut);
		$this->assertCount(1, $remaining);
		$this->assertSame($second, $remaining[0]);
	}

	public function testPopLoggerReturnsFalseForUnknownLogger(): void
	{
		$first  = $this->makeRecorder('first');
		$stranger = $this->makeRecorder('stranger');

		$sut = new ForkedLogger();
		$sut->pushLogger($first);

		$this->assertFalse($sut->popLogger($stranger));

		// The stack must be unchanged.
		$remaining = $this->getLoggers($sut);
		$this->assertCount(1, $remaining);
		$this->assertSame($first, $remaining[0]);

		// And the original logger still receives messages.
		$sut->log('info', 'still here');
		$this->assertCount(1, $first->getMessages());
	}

	/**
	 * pushLogger() short-circuits when the same instance is already on the stack, so the stack
	 * does not contain duplicates. popLogger() must still report success because the instance
	 * really was on the stack — and the stack must end up empty.
	 */
	public function testPopLoggerAfterDeduplicatingPushIsANoOp(): void
	{
		$first = $this->makeRecorder('first');

		$sut = new ForkedLogger();
		$sut->pushLogger($first);
		$sut->pushLogger($first);

		// The dedup means the stack holds exactly one entry.
		$this->assertCount(1, $this->getLoggers($sut));

		$this->assertTrue($sut->popLogger($first));

		$this->assertCount(0, $this->getLoggers($sut));

		$sut->log('info', 'nobody is listening');
		$this->assertCount(0, $first->getMessages());
	}

	public function testClearLoggersRemovesEverything(): void
	{
		$first  = $this->makeRecorder('first');
		$second = $this->makeRecorder('second');

		$sut = new ForkedLogger();
		$sut->pushLogger($first);
		$sut->pushLogger($second);

		$sut->clearLoggers();

		$this->assertCount(0, $this->getLoggers($sut));

		$sut->log('info', 'nobody is listening');
		$this->assertCount(0, $first->getMessages());
		$this->assertCount(0, $second->getMessages());
	}

	/**
	 * Pairs up with testPopLoggerAfterDeduplicatingPushIsANoOp: the structural guarantee the
	 * per-task sites rely on is that pushLogger(id) is idempotent and popLogger(id) is the
	 * matching inverse. A pop before the matching push must be a no-op false return.
	 */
	public function testPopBeforePushReturnsFalse(): void
	{
		$first = $this->makeRecorder('first');

		$sut = new ForkedLogger();

		$this->assertFalse($sut->popLogger($first));
		$this->assertCount(0, $this->getLoggers($sut));
	}
}
