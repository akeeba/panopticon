<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Api\TokenAuthentication;
use Akeeba\Panopticon\Library\Mcp\McpServer;
use Awf\Mvc\Controller;

/**
 * MCP (Model Context Protocol) server controller.
 *
 * Exposes an **optional** MCP server under the `/mcp` route (and `index.php/mcp` for servers without URL rewriting),
 * letting AI agents and chatbots drive Panopticon through the same capabilities as the JSON API.
 *
 * The endpoint implements the MCP *Streamable HTTP* transport in **stateless** mode: each request is self-contained
 * and authenticated by a static HTTP Bearer Authorization header carrying a Panopticon API token. The set of tools an
 * agent can see and call is constrained to exactly what that token's owner could do through the API — see
 * {@see \Akeeba\Panopticon\Library\Mcp\ToolRegistry}.
 *
 * The server must be enabled through the `mcp_enabled` application configuration option; when disabled the endpoint
 * responds as if it does not exist.
 *
 * @since  2.2.0
 */
class Mcp extends Controller
{
	public function __construct($container = null)
	{
		parent::__construct($container);

		// All MCP requests dispatch through the same method, regardless of the requested task.
		$this->registerDefaultTask('dispatch');
	}

	/**
	 * Runs before executing any task.
	 *
	 * Handles CORS preflight, verifies the MCP server is enabled, and authenticates the request using API token
	 * authentication.
	 *
	 * @return  bool  True to continue, false to abort.
	 * @since   2.2.0
	 */
	protected function onBeforeExecute(): bool
	{
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

		// CORS preflight: answer before authentication.
		if ($method === 'OPTIONS')
		{
			$this->sendResponse(204, $this->corsHeaders(), '');

			return false;
		}

		// The MCP server is optional and disabled by default. When off, behave as if the route does not exist.
		if (!$this->getContainer()->appConfig->get('mcp_enabled', false))
		{
			$this->sendResponse(
				404,
				['Content-Type' => 'application/json; charset=utf-8'],
				json_encode(['error' => ['code' => -32601, 'message' => 'The MCP server is not enabled.']])
			);

			return false;
		}

		// Authenticate via API token (static HTTP Bearer Authorization header).
		$auth   = new TokenAuthentication($this->getContainer());
		$userId = $auth->authenticateRequest();

		if ($userId === null)
		{
			$this->sendResponse(
				401,
				[
					'Content-Type'     => 'application/json; charset=utf-8',
					'WWW-Authenticate' => 'Bearer realm="Panopticon MCP"',
				],
				json_encode(['error' => ['code' => -32001, 'message' => 'Invalid or missing API token.']])
			);

			return false;
		}

		return true;
	}

	/**
	 * Dispatch the MCP request according to the HTTP method.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	public function dispatch(): void
	{
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

		switch ($method)
		{
			case 'POST':
				$this->handlePost();
				break;

			case 'DELETE':
				// Stateless server: there is no session to terminate.
				$this->sendResponse(204, $this->corsHeaders(), '');
				break;

			case 'GET':
				// Server-to-client SSE streaming is not supported in stateless mode.
				$this->sendResponse(
					405,
					array_merge($this->corsHeaders(), [
						'Content-Type' => 'application/json; charset=utf-8',
						'Allow'        => 'POST, DELETE, OPTIONS',
					]),
					json_encode(['error' => ['code' => -32601, 'message' => 'GET (SSE) is not supported. Use POST.']])
				);
				break;

			default:
				$this->sendResponse(
					405,
					array_merge($this->corsHeaders(), [
						'Content-Type' => 'application/json; charset=utf-8',
						'Allow'        => 'POST, DELETE, OPTIONS',
					]),
					json_encode(['error' => ['code' => -32601, 'message' => 'Method not allowed.']])
				);
				break;
		}
	}

	/**
	 * Process a POST request carrying a JSON-RPC message.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	private function handlePost(): void
	{
		$rawBody = file_get_contents('php://input') ?: '';

		[$status, $headers, $body] = (new McpServer($this->getContainer()))->handle($rawBody);

		$this->sendResponse($status, array_merge($this->corsHeaders(), $headers), $body);
	}

	/**
	 * The permissive CORS headers advertised for the MCP endpoint.
	 *
	 * @return  array<string,string>
	 * @since   2.2.0
	 */
	private function corsHeaders(): array
	{
		return [
			'Access-Control-Allow-Origin'  => '*',
			'Access-Control-Allow-Methods' => 'POST, DELETE, OPTIONS',
			'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Mcp-Session-Id, MCP-Protocol-Version',
		];
	}

	/**
	 * Emit an HTTP response and terminate the application.
	 *
	 * @param   int                  $status   The HTTP status code.
	 * @param   array<string,string> $headers  The response headers.
	 * @param   string               $body     The response body.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	private function sendResponse(int $status, array $headers, string $body): void
	{
		@ob_end_clean();
		http_response_code($status);

		foreach ($headers as $name => $value)
		{
			header($name . ': ' . $value);
		}

		echo $body;

		$this->getContainer()->application->close();
	}
}
