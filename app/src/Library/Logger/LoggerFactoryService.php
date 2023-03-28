<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Logger;


use Akeeba\Panopticon\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

defined('AKEEBA') || die;

class LoggerFactoryService
{
	private array $loggers = [];

	private array $callbacks = [];

	public function __construct(private readonly Container $container)
	{
	}

	public function get(string $logIdentifier): LoggerInterface
	{
		if (isset($this->loggers[$logIdentifier]))
		{
			return $this->loggers[$logIdentifier];
		}

		$logger = new Logger($logIdentifier);

		$logger->pushHandler(
			new StreamHandler(
				APATH_LOG . '/' . $logIdentifier . '.log',
				$this->container->appConfig->get('debug', false) ? Level::Debug : Level::Warning
			)
		);

		$this->applyCallbacks($logger);

		return $this->loggers[$logIdentifier] = $logger;
	}

	public function addCallback(callable $callback): void
	{
		if (in_array($callback, $this->callbacks))
		{
			return;
		}

		$this->callbacks[] = $callback;
	}

	public function removeCallback(callable $callback): void
	{
		if (!in_array($callback, $this->callbacks))
		{
			return;
		}

		$this->callbacks = array_filter($this->callbacks, fn($x) => $x !== $callback);
	}

	public function pushCallback(callable $callback): void
	{
		$this->addCallback($callback);
	}

	public function popCallback(): ?callable
	{
		if (!count($this->callbacks))
		{
			return null;
		}

		return array_pop($this->callbacks);
	}

	private function applyCallbacks(Logger $logger): void
	{
		foreach ($this->callbacks as $callback)
		{
			$callback($logger);
		}
	}
}