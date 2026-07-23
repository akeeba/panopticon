<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Log;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use ReflectionMethod;

/**
 * Unit tests for Log::getLastLogLines(), the tail reader behind the log viewer.
 *
 * Regression coverage for gh-1036: when a log file is small enough to be read in full (the common
 * case, since the default read buffer is 128 KiB) the first line is complete and must NOT be
 * discarded. The old code called array_shift() unconditionally, dropping the oldest line — turning a
 * one-line log into an empty table.
 *
 * The mid-file path (buffer smaller than the file) must keep its conservative "drop the first line,
 * it might be truncated" behaviour.
 *
 * @since  2.2.1
 */
class LogTest extends AbstractUnitTestCase
{
	/** @var string[] Temporary log files created during a test, cleaned up in tearDown(). */
	private array $tempFiles = [];

	protected function tearDown(): void
	{
		foreach ($this->tempFiles as $file)
		{
			@unlink($file);
		}

		$this->tempFiles = [];

		parent::tearDown();
	}

	/**
	 * gh-1036 headline case: a log with a single, complete entry that fits entirely in the read
	 * buffer must return that one entry, not an empty result.
	 */
	public function testSingleLineLogReadInFullReturnsTheLine(): void
	{
		$path    = $this->makeLogFile([$this->logLine('info', 'The one and only entry')]);
		$results = $this->readTail($path);

		$this->assertCount(1, $results, 'A one-line log read in full must yield exactly one parsed line.');
		$this->assertSame('The one and only entry', $results[0]->message);
	}

	/**
	 * A multi-line log that fits in the buffer must return every line, including the oldest one
	 * (which the unconditional array_shift() used to eat).
	 */
	public function testMultiLineLogReadInFullKeepsTheOldestLine(): void
	{
		$lines = [];

		for ($i = 0; $i < 5; $i++)
		{
			$lines[] = $this->logLine('info', 'Message ' . $i);
		}

		$path    = $this->makeLogFile($lines);
		$results = $this->readTail($path);

		// Results are newest-first (array_reverse), so all five lines must be present with the
		// oldest ("Message 0") landing last.
		$this->assertCount(5, $results);
		$this->assertSame('Message 4', $results[0]->message, 'Newest line must come first.');
		$this->assertSame('Message 0', $results[4]->message, 'Oldest line must be preserved, not dropped.');
	}

	/**
	 * When the file is larger than the read buffer the reader starts part-way through the file. The
	 * first line in the buffer is (potentially) a truncated fragment and must still be discarded.
	 *
	 * We size the buffer to begin exactly at a line boundary, so the first buffered line is actually
	 * complete. This is the only observable case that distinguishes "drop first line when reading
	 * mid-file" from the fully-read behaviour, so it is the precise guard for the $startedMidFile
	 * branch: the line is dropped anyway, conservatively.
	 */
	public function testMidFileReadDiscardsFirstBufferedLine(): void
	{
		$lines = [];

		for ($i = 0; $i < 6; $i++)
		{
			$lines[] = $this->logLine('info', 'Entry ' . $i);
		}

		$content   = implode("\n", $lines) . "\n";
		$totalSize = strlen($content);
		$path      = $this->makeLogFile($lines);

		// Byte offset at which line index 3 starts (length of lines 0..2, each followed by "\n").
		$offsetOfLine3 = strlen($lines[0]) + 1 + strlen($lines[1]) + 1 + strlen($lines[2]) + 1;

		// Buffer size that makes the read start exactly at the beginning of "Entry 3".
		$maxSize = $totalSize - $offsetOfLine3;

		$this->assertLessThan($totalSize, $maxSize, 'Test setup: the buffer must be smaller than the file.');

		$results  = $this->readTail($path, $maxSize);
		$messages = array_map(static fn($x) => $x->message, $results);

		// "Entry 3" is the first buffered line and is conservatively discarded; 4 and 5 survive.
		$this->assertSame(['Entry 5', 'Entry 4'], $messages);
		$this->assertNotContains('Entry 3', $messages, 'The first buffered (potentially truncated) line must be dropped.');
	}

	/**
	 * A file shorter than the 10-byte sanity floor yields no lines regardless of the buffer logic.
	 */
	public function testTinyFileReturnsNothing(): void
	{
		$path    = $this->makeLogFile(['a|b']);
		$results = $this->readTail($path);

		$this->assertSame([], $results);
	}

	/**
	 * Builds a valid full-format log line: TIMESTAMP|LEVEL|FACILITY|MESSAGE|CONTEXT|EXTRA.
	 */
	private function logLine(string $level, string $message): string
	{
		return sprintf('2026-07-23 10:00:00|%s|application|%s|[]|[]', $level, $message);
	}

	/**
	 * Writes the given lines (newline-joined, trailing newline) to a fresh temp file and returns its
	 * path. The file is deleted in tearDown().
	 */
	private function makeLogFile(array $lines): string
	{
		$path = tempnam(sys_get_temp_dir(), 'pnptc-log-') . '.log';
		file_put_contents($path, implode("\n", $lines) . "\n");

		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * Invokes the private Log::getLastLogLines() via reflection.
	 *
	 * @return  object[]
	 */
	private function readTail(string $path, int $maxSize = 131072, int $maxLines = 500): array
	{
		$model  = new Log(Factory::getContainer());
		$method = new ReflectionMethod($model, 'getLastLogLines');
		$method->setAccessible(true);

		return $method->invoke($model, $path, $maxSize, $maxLines);
	}
}
