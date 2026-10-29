<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Main;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application;
use Akeeba\Panopticon\Controller\Main;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;
use Awf\Application\ApplicationServiceProvider;
use Awf\Uri\Uri;

/**
 * The `return` URL of action links in the auto-refreshed dashboard table (gh-1042, gh-1088).
 *
 * JavaScript polls view=main&task=tableBody&format=raw every 30 seconds and inlines the returned
 * <tbody> into the dashboard. Action links in that fragment (e.g. "Reload Joomla! Update
 * Information") take their `return` parameter from Uri::getInstance(). It must point at the
 * dashboard, not the raw AJAX endpoint, or the user lands on a bare HTML fragment after clicking.
 *
 * These tests render the real fragment through the controller, so they cover the view lifecycle
 * (onBefore… → template → onAfter…) and not just the URI rewrite helper. gh-1088 was a restore in
 * onBeforeTableBody()'s finally block undoing the rewrite before the template ran — invisible to
 * the helper's unit tests.
 *
 * @since 2.5.0
 */
class TableBodyReturnUrlTest extends AbstractControllerIntegrationTestCase
{
	private array $savedServer = [];

	protected function setUp(): void
	{
		parent::setUp();

		// The API tests leave a stub application in the shared container. Rendering the templates needs the real one.
		if (!($this->container->application instanceof Application))
		{
			unset($this->container['application']);
			$this->container['application'] = new ApplicationServiceProvider();
		}

		$this->savedServer = $_SERVER;

		$this->loginAs((int) $this->createUser(['parameters' => ['acl.panopticon.super' => 1]])->getId());
		$this->createSite();
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->savedServer;

		Uri::reset();

		parent::tearDown();
	}

	public function testActionLinksReturnToDashboard(): void
	{
		$html = $this->renderTableBody();

		$returnUrls = $this->extractReturnUrls($html);

		// Guard against a vacuous pass: the fragment must actually contain action links.
		$this->assertNotEmpty($returnUrls, 'The rendered tableBody fragment contains no return URLs.');

		foreach ($returnUrls as $returnUrl)
		{
			$uri = new Uri($returnUrl);

			$this->assertSame('main', $uri->getVar('view'), $returnUrl);
			$this->assertFalse($uri->hasVar('task'), $returnUrl);
			$this->assertFalse($uri->hasVar('format'), $returnUrl);
			$this->assertFalse($uri->hasVar('_cacheBustingJunk'), $returnUrl);
		}
	}

	public function testActionLinksKeepRequestState(): void
	{
		$html = $this->renderTableBody(['limitstart' => '0', 'limit' => '20']);

		$returnUrls = $this->extractReturnUrls($html);

		$this->assertNotEmpty($returnUrls, 'The rendered tableBody fragment contains no return URLs.');

		foreach ($returnUrls as $returnUrl)
		{
			$uri = new Uri($returnUrl);

			$this->assertSame('1', $uri->getVar($this->csrfToken()), $returnUrl);
			$this->assertSame('0', $uri->getVar('limitstart'), $returnUrl);
			$this->assertSame('20', $uri->getVar('limit'), $returnUrl);
		}
	}

	public function testRequestUriIsRestoredAfterRendering(): void
	{
		$requestUrl = $this->tableBodyUrl();

		$this->renderTableBody();

		$this->assertSame($requestUrl, Uri::getInstance()->toString());
	}

	/**
	 * The query of the AJAX request the dashboard JavaScript makes.
	 *
	 * @param   array<string,string>  $extra  Additional query parameters (filters, pagination).
	 *
	 * @return  array<string,string>
	 */
	private function tableBodyQuery(array $extra = []): array
	{
		return array_merge(
			[
				'view'              => 'main',
				'task'              => 'tableBody',
				'format'            => 'raw',
				$this->csrfToken()  => '1',
				'_cacheBustingJunk' => '1234567890',
			],
			$extra
		);
	}

	private function tableBodyUrl(array $extra = []): string
	{
		return 'http://panopticon.test/index.php?' . http_build_query($this->tableBodyQuery($extra));
	}

	/**
	 * Render the tableBody fragment as the AJAX request would, and return its HTML.
	 *
	 * The Uri singleton is primed from $_SERVER, exactly as the Dispatcher does for a real request.
	 *
	 * @param   array<string,string>  $extra  Additional query parameters (filters, pagination).
	 *
	 * @return  string
	 */
	private function renderTableBody(array $extra = []): string
	{
		$requestUri = '/index.php?' . http_build_query($this->tableBodyQuery($extra));

		$_SERVER['HTTP_HOST']   = 'panopticon.test';
		$_SERVER['PHP_SELF']    = '/index.php';
		$_SERVER['REQUEST_URI'] = $requestUri;
		unset($_SERVER['HTTPS']);

		Uri::reset();

		$this->assertSame($this->tableBodyUrl($extra), Uri::getInstance()->toString());

		ob_start();

		try
		{
			$this->dispatch(Main::class, 'tableBody', $this->tableBodyQuery($extra), false);
		}
		finally
		{
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Decode every `return` parameter of every link in the HTML.
	 *
	 * @param   string  $html
	 *
	 * @return  string[]
	 */
	private function extractReturnUrls(string $html): array
	{
		preg_match_all('/\bhref="([^"]*)"/', $html, $matches);

		$returnUrls = [];

		foreach ($matches[1] as $href)
		{
			$query = parse_url(html_entity_decode($href), PHP_URL_QUERY) ?: '';

			parse_str($query, $params);

			if (!isset($params['return']) || !is_string($params['return']))
			{
				continue;
			}

			$returnUrls[] = base64_decode($params['return'], true) ?: $params['return'];
		}

		return $returnUrls;
	}
}
