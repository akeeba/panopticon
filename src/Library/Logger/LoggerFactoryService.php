<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Logger;


use Akeeba\Panopticon\Container;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

defined('AKEEBA') || die;

class LoggerFactoryService
{
	private array $loggers = [];

	private array $callbacks = [];

	public const LOG_FORMAT = "%datetime% | %level_name% | %message% | %context% | %extra%\n";

	public function __construct(private readonly Container $container) {}

	public function get(string $logIdentifier, null|int|string|Level $logLevel = null, string $format = self::LOG_FORMAT): LoggerInterface
	{
		if (isset($this->loggers[$logIdentifier]))
		{
			return $this->loggers[$logIdentifier];
		}

		$logger = new Logger($logIdentifier);

		$appConfig     = $this->container->appConfig;
		$logLevel      ??= $appConfig->get('log_level', 'warning');
		$isGlobalDebug = $appConfig->get('debug', false);

		$streamHandler = new StreamHandler(APATH_LOG . '/' . $logIdentifier . '.log', $logLevel);
		$formatter     = new LineFormatter(
			format: $format,
			dateFormat: 'Y-m-d H:i:s.v T',
			allowInlineLineBreaks: false
		);

		$streamHandler->setFormatter($formatter);
		$logger->pushHandler($streamHandler);

		// When global Debug is on, we also fork the output to a global log with its log level set to Debug
		if ($isGlobalDebug)
		{
			$debugStreamHandler = new StreamHandler(APATH_LOG . '/' . 'debug.log', Level::Debug);
			$debugFormatter     = new LineFormatter(
				format: "%datetime% | %level_name% | %channel% | %message% | %context% | %extra%\n",
				dateFormat: 'Y-m-d H:i:s.v T',
				allowInlineLineBreaks: false,
				includeStacktraces: true
			);
			$debugStreamHandler->setFormatter($debugFormatter);

			$logger->pushHandler($debugStreamHandler);
		}

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