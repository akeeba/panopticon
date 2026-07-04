<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Api;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Api\TokenAuthentication;
use Akeeba\Panopticon\Model\Apitoken;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Pure-unit tests for {@see TokenAuthentication}.
 *
 * extractToken() is exercised against $_SERVER / $_GET directly. validateToken() is exercised
 * through a stub Apitoken model returned by a fake Container so the test never touches the DB.
 *
 * @since  1.4.0
 */
class TokenAuthenticationTest extends AbstractUnitTestCase
{
	private array $savedServer;

	private array $savedGet;

	protected function setUp(): void
	{
		parent::setUp();

		$this->savedServer = $_SERVER;
		$this->savedGet    = $_GET;

		// Clear all auth-relevant inputs.
		unset(
			$_SERVER['HTTP_AUTHORIZATION'],
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
			$_SERVER['HTTP_X_PANOPTICON_TOKEN'],
			$_GET['_panopticon_token']
		);
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->savedServer;
		$_GET    = $this->savedGet;

		parent::tearDown();
	}

	// ----------------------------------------------------------------------------------------
	// extractToken
	// ----------------------------------------------------------------------------------------

	public function testExtractTokenReturnsBearerHeader(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer the-token';
		$auth                          = new TokenAuthentication($this->buildContainer());

		$this->assertSame('the-token', $auth->extractToken());
	}

	public function testExtractTokenReturnsRedirectBearerHeader(): void
	{
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer fallback-token';
		$auth                                   = new TokenAuthentication($this->buildContainer());

		$this->assertSame('fallback-token', $auth->extractToken());
	}

	public function testExtractTokenReturnsCustomHeader(): void
	{
		$_SERVER['HTTP_X_PANOPTICON_TOKEN'] = 'header-token';
		$auth                               = new TokenAuthentication($this->buildContainer());

		$this->assertSame('header-token', $auth->extractToken());
	}

	public function testExtractTokenReturnsQueryParam(): void
	{
		$_GET['_panopticon_token'] = 'query-token';
		$auth                      = new TokenAuthentication($this->buildContainer());

		$this->assertSame('query-token', $auth->extractToken());
	}

	public function testExtractTokenBearerWinsOverOthers(): void
	{
		$_SERVER['HTTP_AUTHORIZATION']      = 'Bearer bearer-wins';
		$_SERVER['HTTP_X_PANOPTICON_TOKEN'] = 'header-loses';
		$_GET['_panopticon_token']          = 'query-loses';

		$auth = new TokenAuthentication($this->buildContainer());

		$this->assertSame('bearer-wins', $auth->extractToken());
	}

	public function testExtractTokenReturnsNullWhenAbsent(): void
	{
		$auth = new TokenAuthentication($this->buildContainer());

		$this->assertNull($auth->extractToken());
	}

	public function testExtractTokenFallsBackToGetallheadersBearer(): void
	{
		// Simulate a host where the SetEnvIf trick does not fire: nothing in $_SERVER, but the
		// Authorization header is available via getallheaders(). Header name is deliberately
		// lower-cased to prove the lookup is case-insensitive.
		$auth = $this->buildAuthWithHeaders(
			$this->buildContainer(),
			['authorization' => 'Bearer getallheaders-token']
		);

		$this->assertSame('getallheaders-token', $auth->extractToken());
	}

	public function testExtractTokenFallsBackToGetallheadersCustomHeader(): void
	{
		$auth = $this->buildAuthWithHeaders(
			$this->buildContainer(),
			['X-Panopticon-Token' => 'getallheaders-custom']
		);

		$this->assertSame('getallheaders-custom', $auth->extractToken());
	}

	public function testExtractTokenServerBearerWinsOverGetallheaders(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer server-token';

		$auth = $this->buildAuthWithHeaders(
			$this->buildContainer(),
			['Authorization' => 'Bearer getallheaders-token']
		);

		$this->assertSame('server-token', $auth->extractToken());
	}

	public function testExtractTokenRepairsQueryParamPlusCorruption(): void
	{
		// An unencoded '+' in the query string decodes to a space; extractToken() must restore it.
		$_GET['_panopticon_token'] = 'U0hBLTI1Njo1 abc+def';
		$auth                      = new TokenAuthentication($this->buildContainer());

		$this->assertSame('U0hBLTI1Njo1+abc+def', $auth->extractToken());
	}

	// ----------------------------------------------------------------------------------------
	// validateToken — constant-time invariant
	//
	// Each pathological input must still complete the method without throwing, and the
	// method's structure (audited by code review) guarantees at least one hash_equals call.
	// A wrapping spy is impractical here because hash_equals is a built-in; we cover the
	// invariant via a behavioural smoke test plus a comment pointing to the implementation.
	// ----------------------------------------------------------------------------------------

	public function testValidateTokenRejectsMalformedBase64(): void
	{
		$auth   = new TokenAuthentication($this->buildContainer('site-secret', []));
		$reason = null;

		$result = $auth->validateToken('@@@not-base64@@@', $reason);

		$this->assertNull($result);
		$this->assertSame('malformed', $reason);
	}

	public function testValidateTokenRejectsWrongAlgorithm(): void
	{
		$auth  = new TokenAuthentication($this->buildContainer('site-secret', []));
		$token = base64_encode('MD5:1:abc');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNull($result);
		$this->assertSame('malformed', $reason);
	}

	public function testValidateTokenRejectsNonIntegerUserId(): void
	{
		$auth  = new TokenAuthentication($this->buildContainer('site-secret', []));
		$token = base64_encode('SHA-256:notanint:abc');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNull($result);
		$this->assertSame('malformed', $reason);
	}

	public function testValidateTokenRejectsEmptySecret(): void
	{
		$auth  = new TokenAuthentication($this->buildContainer('', []));
		$token = Apitoken::computeToken(Apitoken::generateSeed(), 1, 'whatever');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNull($result);
		$this->assertSame('no_secret', $reason);
	}

	public function testValidateTokenRejectsZeroEnabledTokensForUser(): void
	{
		// Empty token list — should burn one hash_equals against the dummy then return null.
		$auth  = new TokenAuthentication($this->buildContainer('site-secret', []));
		$seed  = Apitoken::generateSeed();
		$token = Apitoken::computeToken($seed, 99, 'site-secret');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNull($result);
		$this->assertSame('invalid_token', $reason);
	}

	public function testValidateTokenRejectsMismatchedHmac(): void
	{
		$seed = Apitoken::generateSeed();

		$row          = new \stdClass();
		$row->id      = 1;
		$row->user_id = 5;
		$row->seed    = $seed;

		$auth = new TokenAuthentication($this->buildContainer('site-secret', [$row]));

		// Wrong secret used to compute the token => HMAC mismatch.
		$badToken = Apitoken::computeToken($seed, 5, 'WRONG-secret');

		$reason = null;
		$result = $auth->validateToken($badToken, $reason);

		$this->assertNull($result);
		$this->assertSame('invalid_token', $reason);
	}

	public function testValidateTokenAcceptsValidToken(): void
	{
		$seed = Apitoken::generateSeed();

		$row          = new \stdClass();
		$row->id      = 1;
		$row->user_id = 5;
		$row->seed    = $seed;

		$auth  = new TokenAuthentication($this->buildContainer('site-secret', [$row]));
		$token = Apitoken::computeToken($seed, 5, 'site-secret');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNotNull($result);
		$this->assertSame(5, (int) $result->user_id);
		$this->assertNull($reason);
	}

	public function testValidateTokenRejectsExpiredToken(): void
	{
		$seed = Apitoken::generateSeed();

		// SQL normally filters expired rows; this stub mimics a row leaking through. The
		// defence-in-depth check in validateToken() must still reject it.
		$row             = new \stdClass();
		$row->id         = 1;
		$row->user_id    = 5;
		$row->seed       = $seed;
		$row->expires_at = gmdate('Y-m-d H:i:s', time() - 3600);

		$auth  = new TokenAuthentication($this->buildContainer('site-secret', [$row]));
		$token = Apitoken::computeToken($seed, 5, 'site-secret');

		$reason = null;
		$result = $auth->validateToken($token, $reason);

		$this->assertNull($result);
		$this->assertSame('invalid_token', $reason);
	}

	// ----------------------------------------------------------------------------------------
	// helpers
	// ----------------------------------------------------------------------------------------

	/**
	 * Build a TokenAuthentication whose getallheaders()/apache_request_headers() seam returns a
	 * fixed set of headers, so the header-fallback path can be exercised under the CLI SAPI (where
	 * neither function exists).
	 *
	 * @param   \Akeeba\Panopticon\Container  $container  The container to inject.
	 * @param   array<string,string>          $headers    The request headers to expose.
	 */
	private function buildAuthWithHeaders(\Akeeba\Panopticon\Container $container, array $headers): TokenAuthentication
	{
		return new class($container, $headers) extends TokenAuthentication
		{
			public function __construct(\Akeeba\Panopticon\Container $container, private readonly array $fakeHeaders)
			{
				parent::__construct($container);
			}

			protected function getAllRequestHeaders(): array
			{
				return $this->fakeHeaders;
			}
		};
	}

	/**
	 * Build a real Container with overridden `appConfig` and `mvcFactory` services so
	 * TokenAuthentication can be exercised without hitting the DB.
	 */
	private function buildContainer(string $secret = 'site-secret', array $enabledTokens = []): \Akeeba\Panopticon\Container
	{
		$appConfig = new class($secret)
		{
			public function __construct(private readonly string $secret) {}

			public function get(string $key, mixed $default = null): mixed
			{
				if ($key === 'secret')
				{
					return $this->secret;
				}

				return $default;
			}
		};

		$apitokenModel = new class($enabledTokens)
		{
			public function __construct(private readonly array $tokens) {}

			public function getEnabledTokensForUser(int $userId): array
			{
				return $this->tokens;
			}

			public function bind(mixed $row): void {}

			public function recordUse(?string $ipBinary): void {}
		};

		$mvcFactory = new class($apitokenModel)
		{
			public function __construct(private readonly object $apitokenModel) {}

			public function makeTempModel(string $name): object
			{
				if ($name === 'Apitoken')
				{
					return $this->apitokenModel;
				}

				throw new \RuntimeException('Unexpected model: ' . $name);
			}
		};

		return new \Akeeba\Panopticon\Container([
			'appConfig'  => fn() => $appConfig,
			'mvcFactory' => fn() => $mvcFactory,
		]);
	}
}
