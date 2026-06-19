<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Mcp\McpServer;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Exercises the stateless MCP request/response cycle end-to-end through {@see McpServer}.
 *
 * @since  2.2.0
 */
class McpServerTest extends AbstractApiIntegrationTestCase
{
	private function rpc(string $method, array $params = [], int $id = 1): array
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());

		return $this->call($method, $params, $id);
	}

	private function call(string $method, array $params = [], ?int $id = 1): array
	{
		$payload = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];

		if ($id !== null)
		{
			$payload['id'] = $id;
		}

		[$status, $headers, $body] = (new McpServer($this->container))->handle(json_encode($payload));

		return [
			'status'  => $status,
			'headers' => $headers,
			'body'    => $body === '' ? null : json_decode($body, true),
		];
	}

	public function testInitializeReturnsServerInfo(): void
	{
		$response = $this->rpc('initialize', [
			'protocolVersion' => '2025-03-26',
			'capabilities'    => [],
			'clientInfo'      => ['name' => 'phpunit', 'version' => '1'],
		]);

		$this->assertSame(200, $response['status']);
		$this->assertSame('2.0', $response['body']['jsonrpc']);
		$this->assertSame('Akeeba Panopticon', $response['body']['result']['serverInfo']['name']);
		$this->assertArrayHasKey('tools', $response['body']['result']['capabilities']);
	}

	public function testToolsListIsScopeFiltered(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());
		$this->injectTokenScopes(['sites:read']);

		$response = $this->call('tools/list');

		$this->assertSame(200, $response['status']);

		$names = array_map(fn($t) => $t['name'], $response['body']['result']['tools']);

		$this->assertContains('list_sites', $names);
		$this->assertNotContains('list_tasks', $names, 'tasks:read scope was not granted');
	}

	public function testCallingAnUnavailableToolIsMethodNotFound(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());
		$this->injectTokenScopes(['sites:read']); // list_tasks (tasks:read) is NOT registered

		$response = $this->call('tools/call', ['name' => 'list_tasks', 'arguments' => []]);

		$this->assertSame(200, $response['status']);
		$this->assertArrayHasKey('error', $response['body']);
		$this->assertSame(-32601, $response['body']['error']['code']);
	}

	public function testListSitesToolReturnsContent(): void
	{
		$response = $this->rpc('tools/call', ['name' => 'list_sites', 'arguments' => ['limit' => 5]]);

		$this->assertSame(200, $response['status']);
		$this->assertFalse($response['body']['result']['isError']);
		$this->assertSame('text', $response['body']['result']['content'][0]['type']);

		$decoded = json_decode($response['body']['result']['content'][0]['text'], true);
		$this->assertArrayHasKey('sites', $decoded);
		$this->assertArrayHasKey('pagination', $decoded);
	}

	public function testNotificationReturns202WithNoBody(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());

		$response = $this->call('notifications/initialized', [], null);

		$this->assertSame(202, $response['status']);
		$this->assertNull($response['body']);
	}

	public function testInvalidJsonReturnsParseError(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());

		[$status, $headers, $body] = (new McpServer($this->container))->handle('{not json');

		$this->assertSame(400, $status);
		$decoded = json_decode($body, true);
		$this->assertArrayHasKey('error', $decoded);
	}
}
