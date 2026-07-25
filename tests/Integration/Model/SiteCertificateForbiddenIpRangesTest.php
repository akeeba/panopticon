<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;

/**
 * Tests the forbidden IP ranges policy in {@see Site::getCertificateInformation()}.
 *
 * That method is the one outbound connection in the application which cannot go through HttpFactory: fetching a peer
 * certificate requires a raw TLS socket, so the Guzzle middleware never sees it and the check has to be repeated by
 * hand. These tests exist because that hand-written repetition is exactly the kind of thing a later refactor drops.
 *
 * This is hardening adjacent to GHSA-6234-p3mh-7j3x rather than the advisory itself — no request is sent and no
 * response body is returned — but an unguarded outbound connection to an attacker-influenced host is still a usable
 * internal-service probe.
 *
 * @since 1.4.0
 */
class SiteCertificateForbiddenIpRangesTest extends AbstractIntegrationTestCase
{
	/**
	 * An address in TEST-NET-1 (RFC 5737). It is guaranteed not to be routed to anything, so a connection attempt
	 * hangs until the timeout instead of being refused — which is what makes the elapsed-time assertions below
	 * meaningful.
	 */
	private const UNROUTABLE_ADDRESS = '192.0.2.1';

	private mixed $originalRanges = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalRanges = $this->container->appConfig->get('forbidden_ip_ranges', []);
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', $this->originalRanges);

		parent::tearDown();
	}

	private function setForbiddenRanges(array $ranges): void
	{
		$this->container->appConfig->set('forbidden_ip_ranges', $ranges);
	}

	/**
	 * Build a site without going through check(), which would refuse a forbidden URL at save time and leave nothing to
	 * test here. The point is the time-of-check/time-of-use case: a site already in the database whose host has since
	 * moved into a forbidden range.
	 */
	private function makeSite(string $url): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->bind([
			'name'    => 'Certificate Test',
			'url'     => $url,
			'enabled' => 1,
		]);

		return $site;
	}

	/**
	 * The guard must refuse before the socket is opened.
	 *
	 * The elapsed-time assertion is what actually detects a regression. A refused fetch and a failed one both return
	 * NULL, so the return value alone cannot distinguish "the guard fired" from "the connection did not come up"; the
	 * difference is that the guard returns immediately whereas a connection attempt to an unrouted address burns the
	 * full timeout.
	 */
	public function testForbiddenHostIsRefusedWithoutOpeningASocket(): void
	{
		$this->setForbiddenRanges(['192.0.2.0/24']);

		$site = $this->makeSite('https://' . self::UNROUTABLE_ADDRESS . '/');

		$start  = microtime(true);
		$result = $site->getCertificateInformation(10);
		$elapsed = microtime(true) - $start;

		$this->assertNull($result);
		$this->assertLessThan(
			1.0,
			$elapsed,
			'The certificate fetch took long enough to have attempted a connection, so the guard did not fire'
		);
	}

	/**
	 * The loopback case, which is what a self-hosted operator would most plausibly configure.
	 */
	public function testForbiddenLoopbackIsRefused(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('https://127.0.0.1/');

		$this->assertNull($site->getCertificateInformation(10));
	}

	/**
	 * A non-HTTPS site has no certificate to fetch, guard or no guard.
	 */
	public function testNonHttpsSiteReturnsNull(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://127.0.0.1/');

		$this->assertNull($site->getCertificateInformation(10));
	}

	/**
	 * Host resolution fails open, matching the documented behaviour of ForbiddenIpRanges: a transient DNS failure must
	 * not be reported as a policy violation. The fetch still fails — there is nothing to connect to — but it fails
	 * because DNS failed, not because the guard blocked it.
	 */
	public function testUnresolvableHostIsNotTreatedAsForbidden(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('https://this-host-does-not-exist.invalid/');

		$this->assertNull($site->getCertificateInformation(10));
	}

	/**
	 * With the policy disabled the guard must be inert, and crucially must not perform a DNS lookup. This is the
	 * default configuration, and the one every existing installation runs.
	 */
	public function testPolicyDisabledDoesNotRefuse(): void
	{
		$this->setForbiddenRanges([]);

		$site = $this->makeSite('https://' . self::UNROUTABLE_ADDRESS . '/');

		$start  = microtime(true);
		$result = $site->getCertificateInformation(2);
		$elapsed = microtime(true) - $start;

		// Nothing is listening on an unrouted address, so this is NULL either way …
		$this->assertNull($result);

		// … but with the policy disabled it must have got as far as trying, rather than being refused outright.
		$this->assertGreaterThan(
			0.5,
			$elapsed,
			'The certificate fetch returned too quickly to have attempted a connection, so the guard fired despite '
			. 'the policy being disabled'
		);
	}
}
