<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use PhpMcp\Schema\JsonRpc\BatchRequest;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Parser;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\ServerCapabilities;
use PhpMcp\Server\Server;

/**
 * Orchestrates a single stateless MCP request/response cycle for the `/mcp` endpoint.
 *
 * For each request this class assembles a fresh `php-mcp/server` instance containing only the tools the authenticated
 * user is allowed to use (see {@see ToolRegistry}), drives the library's protocol with a {@see SynchronousTransport},
 * and returns the JSON-RPC response so the controller can emit it as the HTTP body.
 *
 * The server runs in **stateless** mode: every request is self-contained and authenticated by the static HTTP Bearer
 * token, with no MCP session handshake required.
 *
 * @since  2.2.0
 */
class McpServer
{
	private const SERVER_NAME = 'Akeeba Panopticon';

	public function __construct(private readonly Container $container) {}

	/**
	 * Process a JSON-RPC request body and return the HTTP response triplet.
	 *
	 * @param   string  $rawBody  The raw POST body (a JSON-RPC message or batch).
	 *
	 * @return  array{0: int, 1: array<string,string>, 2: string}  [HTTP status, headers, body].
	 * @since   2.2.0
	 */
	public function handle(string $rawBody): array
	{
		$jsonHeaders = ['Content-Type' => 'application/json; charset=utf-8'];

		if (trim($rawBody) === '')
		{
			return [400, $jsonHeaders, $this->encode(Error::forInvalidRequest('Empty request body.'))];
		}

		try
		{
			$message = Parser::parse($rawBody);
		}
		catch (\Throwable $e)
		{
			return [400, $jsonHeaders, $this->encode(Error::forParseError('Invalid JSON: ' . $e->getMessage()))];
		}

		// A request-like message must be a Request, Notification or BatchRequest.
		if (!$message instanceof Request && !$message instanceof BatchRequest
			&& !$message instanceof \PhpMcp\Schema\JsonRpc\Notification)
		{
			return [400, $jsonHeaders, $this->encode(Error::forInvalidRequest('Unsupported JSON-RPC message.'))];
		}

		$server    = $this->buildServer();
		$protocol  = $server->getProtocol();
		$transport = new SynchronousTransport();

		$protocol->bindTransport($transport);

		$sessionId = bin2hex(random_bytes(16));
		$server->getSessionManager()->createSession($sessionId);

		try
		{
			$protocol->processMessage($message, $sessionId, ['stateless' => true]);
		}
		finally
		{
			$protocol->unbindTransport();
		}

		$captured = $transport->takeCaptured();

		// Notifications produce no response: acknowledge with 202 Accepted and an empty body.
		if (empty($captured))
		{
			return [202, [], ''];
		}

		return [200, $jsonHeaders, $this->encode($captured[0])];
	}

	/**
	 * Build a php-mcp/server instance pre-loaded with the access-filtered tools for the current request.
	 *
	 * @return  Server
	 * @since   2.2.0
	 */
	private function buildServer(): Server
	{
		$tools   = (new ToolRegistry($this->container))->getAvailableTools();
		$builder = Server::make()
			->withServerInfo(self::SERVER_NAME, $this->getVersion())
			->withCapabilities(ServerCapabilities::make(tools: true))
			->withContainer(new ToolContainer($tools))
			->withInstructions(
				'Akeeba Panopticon monitors and manages Joomla and WordPress sites. Use these tools to inspect '
				. 'sites, their updates, extensions, tasks, and overall health, and to trigger maintenance actions. '
				. 'You can only see and act on the sites the authenticating user has access to.'
			);

		foreach ($tools as $class => $tool)
		{
			$builder->withTool(
				[$class, '__invoke'],
				name: $tool->getName(),
				description: $tool->getDescription(),
				inputSchema: $tool->getInputSchema()
			);
		}

		return $builder->build();
	}

	/**
	 * The Panopticon version string used in the MCP server info.
	 *
	 * @return  string
	 * @since   2.2.0
	 */
	private function getVersion(): string
	{
		return defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'dev';
	}

	/**
	 * JSON-encode a JSON-RPC message.
	 *
	 * @param   object  $message  The message to encode.
	 *
	 * @return  string
	 * @since   2.2.0
	 */
	private function encode(object $message): string
	{
		return json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
