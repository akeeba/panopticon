<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Logger;

defined('AKEEBA') || die;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class MemoryLogger extends AbstractLogger implements LoggerInterface
{
	private $buffer = [];

	public function clear(): void
	{
		$this->buffer = [];
	}

	public function getItems(): array
	{
		return $this->buffer;
	}

	/**
	 * @inheritDoc
	 */
	public function log($level, string|\Stringable $message, array $context = []): void
	{
		$this->buffer[] = (object) [
			'level' => $level,
			'message' => $message,
			'context' => $context,
		];
	}
}