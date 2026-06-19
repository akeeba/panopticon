<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Mcp;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;
use Akeeba\Panopticon\Tests\Integration\Api\ApiResponseException;
use Akeeba\Panopticon\Tests\Integration\Api\PhpInputMock;

/**
 * Exercises the {@see Mcp} controller's HTTP gateway: the enable flag and Bearer-token authentication.
 *
 * @since  2.2.0
 */
class McpControllerTest extends AbstractApiIntegrationTestCase
{
	protected function tearDown(): void
	{
		unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_AUTHORIZATION']);
		PhpInputMock::restore();

		parent::tearDown();
	}

	/**
	 * Drive the Mcp controller and capture the emitted status + body.
	 *
	 * @return  array{status:int, body:array|null, raw:string}
	 */
	private function dispatchMcp(string $method = 'POST', string $rawBody = ''): array
	{
		$_SERVER['REQUEST_METHOD'] = $method;

		if ($rawBody !== '')
		{
			PhpInputMock::set($rawBody);
		}

		$controller = new Mcp($this->container);

		http_response_code(200);

		$baseline = ob_get_level();
		ob_start();
		ob_start();

		try
		{
			$controller->execute('dispatch');
		}
		catch (ApiResponseException)
		{
			// Expected: StubApiApplication::close() throws to unwind.
		}
		catch (\Throwable $e)
		{
			while (ob_get_level() > $baseline)
			{
				ob_end_clean();
			}

			throw $e;
		}

		$raw = '';

		while (ob_get_level() > $baseline + 1)
		{
			ob_end_clean();
		}

		if (ob_get_level() > $baseline)
		{
			$raw = (string) ob_get_clean();
		}

		return [
			'status' => http_response_code() ?: 200,
			'body'   => json_decode($raw, true),
			'raw'    => $raw,
		];
	}

	public function testDisabledServerRespondsAsNotFound(): void
	{
		$this->container->appConfig->set('mcp_enabled', false);

		$response = $this->dispatchMcp('POST', '{"jsonrpc":"2.0","id":1,"method":"ping"}');

		$this->assertSame(404, $response['status']);
	}

	public function testEnabledServerRejectsMissingToken(): void
	{
		$this->container->appConfig->set('mcp_enabled', true);
		unset($_SERVER['HTTP_AUTHORIZATION']);

		$response = $this->dispatchMcp('POST', '{"jsonrpc":"2.0","id":1,"method":"ping"}');

		$this->assertSame(401, $response['status']);
	}

	public function testEnabledServerAcceptsValidTokenAndInitializes(): void
	{
		$this->container->appConfig->set('mcp_enabled', true);

		$user  = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$token = $this->createApiToken((int) $user->getId())['token'];

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

		$body     = '{"jsonrpc":"2.0","id":1,"method":"initialize","params":'
			. '{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"phpunit","version":"1"}}}';
		$response = $this->dispatchMcp('POST', $body);

		$this->assertSame(200, $response['status']);
		$this->assertSame('Akeeba Panopticon', $response['body']['result']['serverInfo']['name']);
	}
}
