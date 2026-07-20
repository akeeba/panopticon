<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\Add;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for the forbidden IP ranges policy on the REST API site-creation path.
 *
 * The advisory (GHSA-6234-p3mh-7j3x) reported the SSRF as reachable through both the web UI and PUT /v1/site. The
 * remediation is implemented once, in Site::check(), which both paths funnel through. These tests exist to prove that
 * claim rather than assume it: if someone later moves the validation into the web controller, these fail.
 *
 * @since 1.4.0
 */
class AddForbiddenIpRangesTest extends AbstractApiIntegrationTestCase
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
	 * Log in as a non-super user holding only `addown` — precisely the low-trust, paying-customer role the advisory
	 * describes in a hosted multi-tenant installation.
	 */
	private function loginAsAddOwnUser(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.addown' => 1]]);

		$this->loginAs((int) $user->getId());
	}

	/**
	 * Create a site through the API handler.
	 *
	 * The handler reads its payload from the request body, not from the query input, so the body has to be installed
	 * explicitly. Getting this wrong makes every test here fail validation for the wrong reason and silently stop
	 * testing anything.
	 *
	 * @param   array<string,mixed>  $payload  The site payload.
	 *
	 * @return  array{status:int, body:array|null, headers:list<string>}
	 */
	private function createSiteViaApi(array $payload): array
	{
		$this->setJsonRequestBody($payload);

		return $this->invokeHandler(Add::class);
	}

	public function testAddOwnUserCannotCreateSiteInForbiddenRange(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8', '169.254.0.0/16']);
		$this->loginAsAddOwnUser();

		$response = $this->createSiteViaApi([
			'name'   => 'Metadata probe',
			'url'    => 'http://169.254.169.254/latest/meta-data/',
			'config' => ['cmsType' => 'joomla'],
		]);

		$this->assertSame(422, $response['status']);
		$this->assertSame('validation.unprocessable', $response['body']['code']);

		// The response must not disclose which address was rejected, or the range which matched it.
		$this->assertStringNotContainsString('169.254.169.254', $response['body']['message'] ?? '');
		$this->assertStringNotContainsString('169.254.0.0/16', $response['body']['message'] ?? '');
	}

	public function testAddOwnUserCannotCreateLoopbackSite(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8']);
		$this->loginAsAddOwnUser();

		$response = $this->createSiteViaApi([
			'name'   => 'Loopback probe',
			'url'    => 'http://127.0.0.1:8080/',
			'config' => ['cmsType' => 'joomla'],
		]);

		$this->assertSame(422, $response['status']);
		$this->assertStringNotContainsString('127.0.0.1', $response['body']['message'] ?? '');

		/**
		 * Pin the rejection to the forbidden-ranges check specifically. Without this the test would still pass if the
		 * payload started failing some unrelated validation, and would silently stop testing anything.
		 */
		$this->assertSame(
			$this->container->language->text('PANOPTICON_SITES_ERR_URL_FORBIDDEN_IP'),
			$response['body']['message'] ?? '',
			'The rejection must come from the forbidden IP ranges check'
		);
	}

	/**
	 * With the policy disabled the API keeps working exactly as before, including for private addresses.
	 */
	public function testPrivateAddressIsAcceptedWhenPolicyIsDisabled(): void
	{
		$this->setForbiddenRanges([]);
		$this->loginAsAddOwnUser();

		$response = $this->createSiteViaApi([
			'name'   => 'LAN site',
			'url'    => 'http://192.168.1.50/',
			'config' => ['cmsType' => 'joomla'],
		]);

		$this->assertLessThan(
			400,
			$response['status'],
			'A private address must be accepted when no forbidden ranges are configured. Response: '
			. json_encode($response['body'])
		);
	}

	/**
	 * A public address is still accepted while a policy is in force.
	 */
	public function testPublicAddressIsAcceptedWhilePolicyIsInForce(): void
	{
		$this->setForbiddenRanges(['127.0.0.0/8', '10.0.0.0/8']);
		$this->loginAsAddOwnUser();

		$response = $this->createSiteViaApi([
			'name'   => 'Public site',
			'url'    => 'http://93.184.216.34/',
			'config' => ['cmsType' => 'joomla'],
		]);

		$this->assertLessThan(
			400,
			$response['status'],
			'A public address must be accepted while a policy is in force. Response: '
			. json_encode($response['body'])
		);
	}
}
