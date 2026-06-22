<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp;

defined('AKEEBA') || die;

use Evenement\EventEmitterTrait;
use PhpMcp\Schema\JsonRpc\Message;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

/**
 * A synchronous, in-memory MCP transport.
 *
 * The `php-mcp/server` library ships HTTP and STDIO transports that are built on a ReactPHP event loop and a
 * long-running process. Neither fits Panopticon, which serves the `/mcp` endpoint through a normal Apache/PHP-FPM
 * request/response cycle.
 *
 * This transport bridges that gap. It implements {@see ServerTransportInterface} but does no networking: when the
 * {@see \PhpMcp\Server\Protocol} hands it a response via {@see self::sendMessage()} it simply captures the message in
 * memory so the calling code can serialise it and emit it as the HTTP response body. All promises resolve
 * immediately, so no event loop is ever run.
 *
 * @since  2.2.0
 */
class SynchronousTransport implements ServerTransportInterface
{
	use EventEmitterTrait;

	/**
	 * Messages captured from the protocol during the current request.
	 *
	 * @var   Message[]
	 * @since 2.2.0
	 */
	private array $captured = [];

	/**
	 * Start the transport listener. A no-op for the synchronous transport.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	public function listen(): void
	{
		// Intentionally empty: there is nothing to listen on.
	}

	/**
	 * Capture a message the protocol wants to send to the client.
	 *
	 * @param   Message  $message    The JSON-RPC message to send.
	 * @param   string   $sessionId  The session identifier (ignored — we are stateless).
	 * @param   array    $context    Optional message context (ignored).
	 *
	 * @return  PromiseInterface  An already-resolved promise.
	 * @since   2.2.0
	 */
	public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
	{
		$this->captured[] = $message;

		return resolve(null);
	}

	/**
	 * Close the transport. Emits the `close` event for protocol cleanup.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	public function close(): void
	{
		$this->emit('close', ['Synchronous transport closed.']);
		$this->removeAllListeners();
	}

	/**
	 * Return the messages captured during the current request and reset the buffer.
	 *
	 * @return  Message[]
	 * @since   2.2.0
	 */
	public function takeCaptured(): array
	{
		$captured       = $this->captured;
		$this->captured = [];

		return $captured;
	}
}
