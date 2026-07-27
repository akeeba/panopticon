<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\View\Main;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use Akeeba\Panopticon\View\Main\Raw;
use Awf\Uri\Uri;

/**
 * Tests for the tableBody URI rewrite helper (gh-1042).
 *
 * The dashboard table is periodically refreshed via an AJAX endpoint
 * (view=main&task=tableBody&format=raw). JavaScript swaps the returned
 * <tbody> into the main page. Action buttons inside the swapped fragment
 * capture Uri::getInstance() for their `return` URL, which would otherwise
 * point at the raw AJAX endpoint. After clicking such a button the user
 * would be redirected to the raw HTML fragment instead of the dashboard.
 *
 * The fix mutates the cached Uri singleton inside Raw::onBeforeTableBody()
 * so that the partial templates capture the main dashboard URL instead. The
 * rewrite logic itself lives in Raw::rewriteUriForTableBodyFragment(), a
 * pure function on a passed-in Uri instance so it can be tested without
 * touching the global singleton.
 *
 * @since 2.3.1
 */
class RawViewTest extends AbstractUnitTestCase
{
	private function makeTableBodyUri(string $extra = ''): Uri
	{
		return new Uri(
			'http://example.com/'
			. '?view=main&task=tableBody&format=raw&abcdef0123456789=1&_cacheBustingJunk=1.234'
			. ($extra === '' ? '' : '&' . $extra)
		);
	}

	public function testRewriteStripsAjaxTask(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertFalse($uri->hasVar('task'));
	}

	public function testRewriteStripsRawFormat(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertFalse($uri->hasVar('format'));
	}

	public function testRewriteStripsCacheBustingJunk(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertFalse($uri->hasVar('_cacheBustingJunk'));
	}

	public function testRewriteSetsViewToMain(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertSame('main', $uri->getVar('view'));
	}

	public function testRewriteSetsViewToMainWhenOriginallyMissing(): void
	{
		$uri = new Uri('http://example.com/?task=tableBody&format=raw');

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertSame('main', $uri->getVar('view'));
	}

	public function testRewritePreservesCsrfToken(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertSame('1', $uri->getVar('abcdef0123456789'));
	}

	public function testRewritePreservesFilterState(): void
	{
		$uri = $this->makeTableBodyUri(
			'search=foo&coreUpdates=1&extUpdates=0&cmsFamily=4&phpFamily=8.2&limitstart=50&limit=20'
		);

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertSame('foo', $uri->getVar('search'));
		$this->assertSame('1', $uri->getVar('coreUpdates'));
		$this->assertSame('0', $uri->getVar('extUpdates'));
		$this->assertSame('4', $uri->getVar('cmsFamily'));
		$this->assertSame('8.2', $uri->getVar('phpFamily'));
		$this->assertSame('50', $uri->getVar('limitstart'));
		$this->assertSame('20', $uri->getVar('limit'));
	}

	public function testRewriteFullUrlNoLongerContainsAjaxParams(): void
	{
		$uri = $this->makeTableBodyUri('search=foo');

		Raw::rewriteUriForTableBodyFragment($uri);

		$url = $uri->toString();

		$this->assertStringNotContainsString('task=', $url);
		$this->assertStringNotContainsString('format=', $url);
		$this->assertStringNotContainsString('_cacheBustingJunk=', $url);
	}

	public function testRewriteFullUrlContainsDashboardViewAndCsrfToken(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);

		$url = $uri->toString();

		$this->assertStringContainsString('view=main', $url);
		$this->assertStringContainsString('abcdef0123456789=1', $url);
	}

	public function testRewriteIsIdempotent(): void
	{
		$uri = $this->makeTableBodyUri();

		Raw::rewriteUriForTableBodyFragment($uri);
		$first  = $uri->toString();
		$view   = $uri->getVar('view');

		Raw::rewriteUriForTableBodyFragment($uri);
		$second = $uri->toString();

		$this->assertSame($first, $second);
		$this->assertSame('main', $view);
	}

	public function testRewriteReturnsBaseUrlWhenAllParamsWereAjaxSpecific(): void
	{
		$uri = new Uri('http://example.com/?view=main&task=tableBody&format=raw');

		Raw::rewriteUriForTableBodyFragment($uri);

		$this->assertSame('http://example.com/?view=main', $uri->toString());
	}
}