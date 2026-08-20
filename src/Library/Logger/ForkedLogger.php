<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
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

	/**
	 * Remove a logger previously pushed with pushLogger().
	 *
	 * @param   LoggerInterface  $logger  The logger to remove.
	 *
	 * @return  bool  True if the logger was found and removed, false otherwise.
	 *
	 * @since   2.3.2
	 */
	public function popLogger(LoggerInterface $logger): bool
	{
		$key = array_search($logger, $this->loggers, true);

		if ($key === false)
		{
			return false;
		}

		unset($this->loggers[$key]);
		$this->loggers = array_values($this->loggers);

		return true;
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