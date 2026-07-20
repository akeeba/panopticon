<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Library\Http;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Security\ForbiddenHostException;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests connection-time enforcement of the forbidden IP ranges policy.
 *
 * This is the authoritative half of the remediation for GHSA-6234-p3mh-7j3x. The save-time check in Site::check() is
 * subject to time-of-check/time-of-use — DNS may change after a site is saved — so the guarantee has to be enforced
 * where the request is actually made.
 *
 * The redirect tests are the important ones. A save-time check alone is trivially bypassed by pointing a site at a
 * host the attacker controls which then issues a 302 into the forbidden range, so the guard has to re-evaluate every
 * hop, not just the initial request.
 *
 * All requests are served by a MockHandler; no test here touches the network.
 *
 * @since 1.4.0
 */
class ForbiddenHostMiddlewareTest extends AbstractIntegrationTestCase
{
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
	 * Build a client whose transport is a queue of canned responses.
	 *
	 * Caching and client memoisation are both disabled so that each test gets a genuinely fresh stack.
	 *
	 * @param   array<Response>  $responses  The responses to serve, in order.
	 */
	private function makeMockedClient(array $responses): \GuzzleHttp\Client
	{
		$stack = HandlerStack::create(new MockHandler($responses));

		return $this->container->httpFactory->makeClient(
			stack: $stack,
			cache: false,
			singleton: false
		);
	}

	/**
	 * With the policy disabled the guard is inert and requests proceed normally.
	 */
	public function testRequestToPrivateAddressSucceedsWhenPolicyIsDisabled(): void
	{
		$this->setForbiddenRanges([]);

		$client   = $this->makeMockedClient([new Response(200, [], 'OK')]);
		$response = $client->get('http://127.0.0.1/');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('OK', (string) $response->getBody());
	}

	/**
	 * A direct request into a forbidden range is refused before it is dispatched.
	 */
	public function testDirectRequestToForbiddenHostIsRefused(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$client = $this->makeMockedClient([new Response(200, [], 'should never be reached')]);

		$this->expectException(ForbiddenHostException::class);

		$client->get('http://127.0.0.1/');
	}

	public function testDirectRequestToMetadataEndpointIsRefused(): void
	{
		$this->setForbiddenRanges(['169.254.0.0/16']);

		$client = $this->makeMockedClient([new Response(200, [], 'instance credentials')]);

		try
		{
			$client->get('http://169.254.169.254/latest/meta-data/');

			$this->fail('Expected the request to the metadata endpoint to be refused');
		}
		catch (ForbiddenHostException $e)
		{
			$this->assertSame('169.254.169.254', $e->getHost());
		}
	}

	/**
	 * A request to a permitted host still works while a policy is in force.
	 */
	public function testRequestToPermittedHostSucceedsWhilePolicyIsInForce(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8', '10.0.0.0/8']);

		$client   = $this->makeMockedClient([new Response(200, [], 'OK')]);
		$response = $client->get('http://93.184.216.34/');

		$this->assertSame(200, $response->getStatusCode());
	}

	/**
	 * THE bypass this whole commit exists to close: a permitted host which redirects into a forbidden range.
	 *
	 * If the guard only ran on the initial request this would return 200 and hand the caller the internal response.
	 */
	public function testRedirectIntoForbiddenRangeIsRefused(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$client = $this->makeMockedClient([
			new Response(302, ['Location' => 'http://127.0.0.1/secret']),
			new Response(200, [], 'internal secret'),
		]);

		try
		{
			$client->get('http://93.184.216.34/');

			$this->fail('Expected the redirect hop into the forbidden range to be refused');
		}
		catch (ForbiddenHostException $e)
		{
			$this->assertSame('127.0.0.1', $e->getHost());
		}
	}

	/**
	 * The same bypass, one hop deeper, to prove the guard is not merely checking the second request.
	 */
	public function testMultiHopRedirectIntoForbiddenRangeIsRefused(): void
	{
		$this->setForbiddenRanges(['169.254.0.0/16']);

		$client = $this->makeMockedClient([
			new Response(302, ['Location' => 'http://93.184.216.35/next']),
			new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
			new Response(200, [], 'instance credentials'),
		]);

		try
		{
			$client->get('http://93.184.216.34/');

			$this->fail('Expected the second redirect hop into the forbidden range to be refused');
		}
		catch (ForbiddenHostException $e)
		{
			$this->assertSame('169.254.169.254', $e->getHost());
		}
	}

	/**
	 * A redirect chain which stays entirely within permitted space must still work.
	 */
	public function testRedirectToPermittedHostSucceeds(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$client = $this->makeMockedClient([
			new Response(302, ['Location' => 'http://93.184.216.35/elsewhere']),
			new Response(200, [], 'fine'),
		]);

		$response = $client->get('http://93.184.216.34/');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('fine', (string) $response->getBody());
	}

	/**
	 * The policy is read per request, not captured when the client is built. Clients are memoised by signature, so a
	 * policy captured at build time would go stale and silently stop protecting anything.
	 */
	public function testPolicyChangesTakeEffectWithoutRebuildingTheClient(): void
	{
		$this->setForbiddenRanges([]);

		$client = $this->makeMockedClient([
			new Response(200, [], 'first'),
			new Response(200, [], 'second'),
		]);

		$this->assertSame(200, $client->get('http://127.0.0.1/')->getStatusCode());

		// Tighten the policy on the very same client instance.
		$this->setForbiddenRanges(['127.0.0.0/8']);

		$this->expectException(ForbiddenHostException::class);

		$client->get('http://127.0.0.1/');
	}
}
