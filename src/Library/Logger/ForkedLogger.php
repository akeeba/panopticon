<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

defined('AKEEBA') || die;

class ForkedLogger extends AbstractLogger implements LoggerInterface
{
	/**
	 * @var LoggerInterface[]
	 */
	private array $loggers = [];

	public function __construct($loggers = [])
	{
		foreach ($loggers as $logger)
		{
			$this->pushLogger($logger);
		}
	}

	public function pushLogger(LoggerInterface $logger): void
	{
		if ($this->hasLogger($logger))
		{
			return;
		}

		$this->loggers[] = $logger;
	}

	public function hasLogger(LoggerInterface $logger): bool
	{
		return in_array($logger, $this->loggers, true);
	}

	public function clearLoggers(): void
	{
		$this->loggers = [];
	}

	public function log($level, $message, array $context = []): void
	{
		foreach ($this->loggers as $logger)
		{
			$logger->log($level, $message, $context);
		}
	}
}