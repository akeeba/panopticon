<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\WebServer;

defined('AKEEBA') || die;

/**
 * Web-server integration tests for the MCP endpoint.
 *
 * Exercises the transport matrix that gh-1010 lived in, through a real Apache + PHP container:
 * the .htaccess rewrite + Authorization passthrough, the /mcp vs /index.php/mcp URL forms, and the
 * header/query-parameter token fallbacks. Scope is deliberately narrow — every request lists the
 * MCP tools (`tools/list`) and we assert on the HTTP status and, for 200s, the returned tools.
 *
 * @since  2.3.0
 */
class McpWebServerTest extends AbstractWebServerTestCase
{
	/**
	 * Without .htaccess, PHP-FPM never sees the stripped Authorization header, so a Bearer token
	 * is rejected (401) — the real gh-1010 failure. Under mod_php the getallheaders() fallback
	 * recovers the header, so the same request succeeds (200). This is the one cell that covers
	 * the mod_php half of the fix.
	 */
	public function testIndexPhpMcpWithBearerWithoutHtaccess(): void
	{
		$this->disableHtaccess();

		$result = $this->listTools('/index.php/mcp', 'bearer');

		if ($this->sapi() === 'modphp')
		{
			$this->assertToolsListed($result);
		}
		else
		{
			$this->assertSame(
				401,
				$result['status'],
				'Under PHP-FPM without .htaccess the Authorization header is stripped, so auth must fail.'
			);
		}
	}

	/**
	 * The X-Panopticon-Token header is not stripped by the web server, so it authenticates even
	 * on a bare host without .htaccess. (Locks the custom-header fallback from gh-1010.)
	 */
	public function testIndexPhpMcpWithCustomHeaderWithoutHtaccess(): void
	{
		$this->disableHtaccess();

		$this->assertToolsListed($this->listTools('/index.php/mcp', 'x-header'));
	}

	/**
	 * The _panopticon_token query parameter travels in the URL and is never stripped, so it too
	 * authenticates without .htaccess. (Locks the query-parameter fallback from gh-1010.)
	 */
	public function testIndexPhpMcpWithQueryParamWithoutHtaccess(): void
	{
		$this->disableHtaccess();

		$this->assertToolsListed($this->listTools('/index.php/mcp', 'query'));
	}

	/**
	 * Without .htaccess there is no rewrite for the short /mcp URL, so it is a plain filesystem
	 * 404. (Locks the "missing mcp rewrite" half of gh-1010.)
	 */
	public function testShortMcpIsNotFoundWithoutHtaccess(): void
	{
		$this->disableHtaccess();

		$result = $this->listTools('/mcp', 'x-header');

		$this->assertSame(
			404,
			$result['status'],
			'Without the .htaccess rewrite, /mcp must be a filesystem 404.'
		);
	}

	/**
	 * With .htaccess present, the SetEnvIf trick passes Authorization through to PHP-FPM, so a
	 * Bearer token on the PATH_INFO URL authenticates.
	 */
	public function testIndexPhpMcpWithBearerWithHtaccess(): void
	{
		$this->enableHtaccess();

		$this->assertToolsListed($this->listTools('/index.php/mcp', 'bearer'));
	}

	/**
	 * With .htaccess present, the mcp rewrite maps /mcp to index.php/mcp and SetEnvIf passes the
	 * Authorization header, so the short clean URL works end-to-end.
	 */
	public function testShortMcpWithBearerWithHtaccess(): void
	{
		$this->enableHtaccess();

		$this->assertToolsListed($this->listTools('/mcp', 'bearer'));
	}

	/**
	 * A reachable endpoint still rejects a request with no token at all.
	 */
	public function testMissingTokenIsUnauthorized(): void
	{
		$this->enableHtaccess();

		$result = $this->listTools('/index.php/mcp', 'none');

		$this->assertSame(401, $result['status'], 'A request with no token must be rejected.');
	}

	/**
	 * Assert a successful `tools/list`: HTTP 200 and a non-empty tools array containing the
	 * known read-only tools a super-user token exposes.
	 *
	 * @param   array{status:int, body:array|null, raw:string}  $result
	 */
	private function assertToolsListed(array $result): void
	{
		$this->assertSame(200, $result['status'], 'Expected HTTP 200. Raw body: ' . $result['raw']);

		$names = $this->toolNames($result['body']);

		$this->assertNotEmpty($names, 'Expected a non-empty tools list. Raw body: ' . $result['raw']);
		$this->assertContains('list_sites', $names, 'Expected the list_sites tool to be present.');
		$this->assertContains('get_site', $names, 'Expected the get_site tool to be present.');
	}
}
