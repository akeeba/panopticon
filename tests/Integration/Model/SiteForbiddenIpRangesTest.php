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
use RuntimeException;

/**
 * Tests the forbidden IP ranges policy enforced by {@see Site::check()}.
 *
 * This is the save-time half of the remediation for GHSA-6234-p3mh-7j3x. It guards the boundary which matters in a
 * multi-tenant installation: a user with only the `addown` privilege must not be able to point a site definition at
 * the operator's internal network and have Panopticon fetch it for them.
 *
 * The policy is disabled by default, so the "no ranges configured" tests below are just as important as the blocking
 * ones — they guard against a regression which would break every self-hosted installation monitoring its own LAN.
 *
 * @since 1.4.0
 */
class SiteForbiddenIpRangesTest extends AbstractIntegrationTestCase
{
	/**
	 * Restore the configuration after each test so one test cannot leak its policy into the next.
	 *
	 * The application configuration is not part of the database transaction which
	 * {@see AbstractIntegrationTestCase} rolls back, so it has to be reset by hand.
	 */
	private mixed $originalRanges = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalRanges = $this->container->appConfig->get('forbidden_ip_ranges', []);

		// Site::check() populates the audit fields from the current user and rejects an empty created_by, so the
		// tests must run as a logged-in user. Without this every assertion below would pass or fail for the wrong
		// reason.
		$this->loginAsUser();
	}

	/**
	 * Set a real logged-in user as the current user of the user manager.
	 */
	private function loginAsUser(): void
	{
		$user    = $this->createUser();
		$manager = $this->container->userManager;

		$reflection = new \ReflectionObject($manager);
		$property   = $reflection->getProperty('currentUser');
		$property->setValue($manager, $manager->getUser($user->getId()));
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

	private function makeSite(string $url): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->bind([
			'name'    => 'Forbidden Range Test',
			'url'     => $url,
			'enabled' => 1,
		]);

		return $site;
	}

	/**
	 * With no ranges configured the feature is inert. This is the default, and the behaviour every existing
	 * single-tenant installation depends on.
	 */
	public function testLoopbackIsAllowedWhenPolicyIsDisabled(): void
	{
		$this->setForbiddenRanges([]);

		$site = $this->makeSite('http://127.0.0.1/');
		$site->check();

		$this->assertStringContainsString('127.0.0.1', $site->url);
	}

	public function testPrivateAddressIsAllowedWhenPolicyIsDisabled(): void
	{
		$this->setForbiddenRanges([]);

		$site = $this->makeSite('http://192.168.1.50/');
		$site->check();

		$this->assertStringContainsString('192.168.1.50', $site->url);
	}

	/**
	 * The proof-of-concept from the advisory: the cloud instance metadata endpoint.
	 */
	public function testMetadataEndpointIsBlockedWhenListed(): void
	{
		$this->setForbiddenRanges(['169.254.169.254']);

		$site = $this->makeSite('http://169.254.169.254/latest/meta-data/');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	public function testLoopbackIsBlockedWhenListed(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://127.0.0.1/');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	public function testLoopbackOnNonStandardPortIsBlockedWhenListed(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://127.0.0.1:8080/');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	public function testRfc1918AddressIsBlockedWhenListed(): void
	{
		$this->setForbiddenRanges(['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

		$site = $this->makeSite('http://192.168.1.50/');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	public function testIpv6LoopbackLiteralIsBlockedWhenListed(): void
	{
		$this->setForbiddenRanges(['::1']);

		$site = $this->makeSite('http://[::1]/');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	/**
	 * A public address must still be accepted while a policy is in force. If this fails the feature has become a
	 * blanket denial and would break every legitimate site.
	 */
	public function testPublicAddressIsAllowedWhilePolicyIsInForce(): void
	{
		$this->setForbiddenRanges(['10.0.0.0/8', '127.0.0.0/8', '192.168.0.0/16']);

		$site = $this->makeSite('http://93.184.216.34/');
		$site->check();

		$this->assertStringContainsString('93.184.216.34', $site->url);
	}

	/**
	 * An address just outside a configured range must be accepted: off-by-one in the matcher would be a silent
	 * availability bug.
	 */
	public function testAddressJustOutsideRangeIsAllowed(): void
	{
		$this->setForbiddenRanges(['192.168.1.0/24']);

		$site = $this->makeSite('http://192.168.2.1/');
		$site->check();

		$this->assertStringContainsString('192.168.2.1', $site->url);
	}

	/**
	 * An unresolvable host fails open at save time, by design, so that a transient DNS failure cannot block a
	 * legitimate edit. Connection-time enforcement is the backstop.
	 */
	public function testUnresolvableHostIsAllowed(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://this-host-does-not-exist.invalid/');
		$site->check();

		$this->assertStringContainsString('this-host-does-not-exist.invalid', $site->url);
	}

	/**
	 * The check must run against the URL as normalised by cleanUrl(), which appends the CMS API path. Validating the
	 * pre-normalisation URL would leave a gap if normalisation ever altered the host.
	 */
	public function testCheckRunsAgainstTheNormalisedUrl(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://127.0.0.1');

		$this->expectException(RuntimeException::class);

		$site->check();
	}

	/**
	 * The error message must name the offending host so the operator can act on it.
	 */
	public function testErrorMessageNamesTheHost(): void
	{
		$this->setForbiddenRanges(['10.0.0.0/8']);

		$site = $this->makeSite('http://10.1.2.3/');

		try
		{
			$site->check();

			$this->fail('Expected the check to reject a forbidden host');
		}
		catch (RuntimeException $e)
		{
			$this->assertStringContainsString('10.1.2.3', $e->getMessage());
		}
	}

	/**
	 * A blocked site must not reach the database. A validation error which still persisted the row would leave the
	 * forbidden URL available to the background task scheduler.
	 */
	public function testBlockedSiteIsNotPersisted(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$site = $this->makeSite('http://127.0.0.1/');

		try
		{
			$site->save();
		}
		catch (RuntimeException)
		{
			// Expected
		}

		$this->assertEmpty($site->getId(), 'A site with a forbidden URL must not be saved');
	}
}
