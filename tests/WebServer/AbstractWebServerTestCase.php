<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\WebServer;

defined('AKEEBA') || die;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the web-server MCP integration tests.
 *
 * These tests drive a live Dockerised Apache + PHP container (brought up by run.sh) over real
 * HTTP, so they can observe transport-layer behaviour the in-process tests cannot: a web server
 * that strips the Authorization header, and a .htaccess rewrite that does or does not fire.
 *
 * The harness passes state in via environment variables:
 *   PANOPTICON_TEST_BASE_URL   e.g. http://localhost:4290
 *   PANOPTICON_TEST_TOKEN      a valid API token minted inside the container
 *   PANOPTICON_TEST_CONTAINER  the container name, for toggling .htaccess via `docker exec`
 *   PANOPTICON_TEST_SAPI       'fpm' (default) or 'modphp'
 *
 * When those are absent (e.g. a bare `phpunit`), every test skips cleanly.
 *
 * @since  2.3.0
 */
abstract class AbstractWebServerTestCase extends TestCase
{
	protected string $baseUrl;

	protected string $token;

	protected string $container;

	protected Client $client;

	protected function setUp(): void
	{
		$baseUrl   = getenv('PANOPTICON_TEST_BASE_URL') ?: '';
		$token     = getenv('PANOPTICON_TEST_TOKEN') ?: '';
		$container = getenv('PANOPTICON_TEST_CONTAINER') ?: '';

		if ($baseUrl === '' || $token === '' || $container === '')
		{
			$this->markTestSkipped(
				'Web-server integration tests require a running container. Run `composer test:webserver`.'
			);
		}

		$this->baseUrl   = rtrim($baseUrl, '/');
		$this->token     = $token;
		$this->container = $container;

		$this->client = new Client(
			[
				'base_uri'    => $this->baseUrl,
				'http_errors' => false,
				'timeout'     => 20,
			]
		);
	}

	/**
	 * The PHP SAPI the container is running: 'fpm' (default) or 'modphp'.
	 */
	protected function sapi(): string
	{
		return getenv('PANOPTICON_TEST_SAPI') ?: 'fpm';
	}

	/**
	 * Copy htaccess.txt to .htaccess inside the container (enables the mcp rewrite + the
	 * SetEnvIf Authorization passthrough). Effective immediately — AllowOverride All means
	 * Apache re-reads .htaccess on every request, no restart needed.
	 */
	protected function enableHtaccess(): void
	{
		$this->dockerExec(['cp', '/var/www/html/htaccess.txt', '/var/www/html/.htaccess']);
	}

	/**
	 * Remove .htaccess inside the container (the shipped default: no rewrite, no SetEnvIf).
	 */
	protected function disableHtaccess(): void
	{
		$this->dockerExec(['rm', '-f', '/var/www/html/.htaccess']);
	}

	/**
	 * Send a `tools/list` JSON-RPC request and return the decoded HTTP result.
	 *
	 * @param   string  $path       Endpoint path, e.g. '/index.php/mcp' or '/mcp'.
	 * @param   string  $transport  How to present the token: 'bearer', 'x-header', 'query', 'none'.
	 *
	 * @return  array{status:int, body:array|null, raw:string}
	 */
	protected function listTools(string $path, string $transport): array
	{
		$headers = ['Content-Type' => 'application/json'];
		$url     = $path;

		switch ($transport)
		{
			case 'bearer':
				$headers['Authorization'] = 'Bearer ' . $this->token;
				break;

			case 'x-header':
				$headers['X-Panopticon-Token'] = $this->token;
				break;

			case 'query':
				$url .= (str_contains($path, '?') ? '&' : '?') . '_panopticon_token=' . rawurlencode($this->token);
				break;

			case 'none':
				break;

			default:
				throw new \InvalidArgumentException(sprintf('Unknown transport "%s".', $transport));
		}

		$response = $this->client->post(
			$url,
			[
				'headers' => $headers,
				'body'    => '{"jsonrpc":"2.0","id":1,"method":"tools/list"}',
			]
		);

		$raw     = (string) $response->getBody();
		$decoded = json_decode($raw, true);

		return [
			'status' => $response->getStatusCode(),
			'body'   => is_array($decoded) ? $decoded : null,
			'raw'    => $raw,
		];
	}

	/**
	 * Extract the tool names from a decoded `tools/list` response body.
	 *
	 * @param   array|null  $body
	 *
	 * @return  string[]
	 */
	protected function toolNames(?array $body): array
	{
		$tools = $body['result']['tools'] ?? [];

		return array_values(array_filter(array_map(
			static fn($tool) => is_array($tool) ? ($tool['name'] ?? null) : null,
			is_array($tools) ? $tools : []
		)));
	}

	/**
	 * Run a command inside the test container via `docker exec`, asserting it succeeds.
	 *
	 * @param   string[]  $command
	 */
	private function dockerExec(array $command): void
	{
		$parts = array_merge(['docker', 'exec', $this->container], $command);
		$cmd   = implode(' ', array_map('escapeshellarg', $parts));

		exec($cmd . ' 2>&1', $output, $exitCode);

		$this->assertSame(
			0,
			$exitCode,
			sprintf("`%s` failed (exit %d):\n%s", $cmd, $exitCode, implode("\n", $output))
		);
	}
}
