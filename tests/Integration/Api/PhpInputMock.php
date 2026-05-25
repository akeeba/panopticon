<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api;

defined('AKEEBA') || die;

/**
 * Stream wrapper that replaces `php://input` with a fixed string for the duration of a test.
 *
 * Usage:
 *   PhpInputMock::set('{"name": "x"}');   // installs the wrapper
 *   ...invoke handler...
 *   PhpInputMock::restore();              // unregisters the wrapper
 *
 * @since  1.4.0
 *
 * @phpstan-type StreamContext resource
 */
final class PhpInputMock
{
	/** @var string */
	private static string $payload = '';

	private static bool $registered = false;

	/** @var resource|null Stream context, set by PHP when the wrapper is opened. */
	public $context;

	private int $position = 0;

	public static function set(string $payload): void
	{
		self::$payload = $payload;

		if (!self::$registered)
		{
			stream_wrapper_unregister('php');
			stream_wrapper_register('php', self::class);
			self::$registered = true;
		}
	}

	public static function restore(): void
	{
		if (self::$registered)
		{
			stream_wrapper_restore('php');
			self::$registered = false;
		}

		self::$payload = '';
	}

	public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
	{
		$this->position = 0;

		return true;
	}

	public function stream_read(int $count): string
	{
		$chunk = substr(self::$payload, $this->position, $count);
		$this->position += strlen($chunk);

		return $chunk;
	}

	public function stream_eof(): bool
	{
		return $this->position >= strlen(self::$payload);
	}

	public function stream_stat(): array
	{
		return [
			'size' => strlen(self::$payload),
		];
	}

	public function stream_tell(): int
	{
		return $this->position;
	}

	public function stream_seek(int $offset, int $whence = SEEK_SET): bool
	{
		switch ($whence)
		{
			case SEEK_SET:
				$this->position = $offset;
				break;

			case SEEK_CUR:
				$this->position += $offset;
				break;

			case SEEK_END:
				$this->position = strlen(self::$payload) + $offset;
				break;
		}

		return true;
	}

	public function stream_close(): void
	{
		$this->position = 0;
	}
}
