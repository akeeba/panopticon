<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Input\Input;

/**
 * Base class for HTTP-API integration tests.
 *
 * Provides:
 * - A stub Application registered into the container whose `close()` throws
 *   {@see ApiResponseException} instead of calling `exit()`. This lets us unwind
 *   handler execution at the point a response would have been emitted.
 * - {@see invokeHandler()} which wraps the call in an output buffer, catches the
 *   sentinel exception, decodes the JSON body and returns a structured result.
 *
 * @since  1.4.0
 */
abstract class AbstractApiIntegrationTestCase extends AbstractIntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Replace the container's `application` service with a non-exiting stub.
		// We hold a reference so the closure can resolve it lazily.
		$container = $this->container;

		// Pimple disallows overwriting an already-resolved value, so we use offsetUnset()
		// then re-set. The application service might already be resolved by other tests
		// sharing the same Container.
		if (isset($container['application']))
		{
			unset($container['application']);
		}

		$container['application'] = function () use ($container)
		{
			return new StubApiApplication($container);
		};
	}

	protected function tearDown(): void
	{
		// Reset $_SERVER / $_GET so consecutive tests don't bleed auth state.
		unset(
			$_SERVER['HTTP_AUTHORIZATION'],
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
			$_SERVER['HTTP_X_PANOPTICON_TOKEN'],
			$_GET['_panopticon_token']
		);

		// Restore the `php://` stream wrapper in case a test used PhpInputMock and forgot to.
		PhpInputMock::restore();

		parent::tearDown();
	}

	/**
	 * Set a JSON body that handlers will see when calling `file_get_contents('php://input')`.
	 *
	 * @param   array|string  $body  Either an array (json-encoded for you) or a raw string.
	 */
	protected function setJsonRequestBody(array|string $body): void
	{
		$raw = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $body;

		PhpInputMock::set((string) $raw);
	}

	/**
	 * Build an Input object from query params.
	 *
	 * @param   array<string,mixed>  $get
	 *
	 * @return  Input
	 */
	protected function makeInput(array $get = []): Input
	{
		return new Input($get);
	}

	/**
	 * Invoke a handler and capture its response.
	 *
	 * @param   string                       $handlerClass  Fully-qualified class name.
	 * @param   array<string,mixed>          $inputData     Data passed to Input ctor.
	 *
	 * @return  array{status:int, body:array|null, headers:list<string>}
	 */
	protected function invokeHandler(string $handlerClass, array $inputData = []): array
	{
		$input   = $this->makeInput($inputData);
		$handler = new $handlerClass($this->container, $input);

		http_response_code(200);

		// Two stacked output buffers: the handler's internal @ob_end_clean() closes the
		// innermost (sacrificial) one; the outer captures the JSON echo. Track the baseline
		// ob level so we can normalise it precisely on the way out and keep PHPUnit's
		// risky-test detector happy.
		$baselineObLevel = ob_get_level();
		ob_start();
		ob_start();

		try
		{
			$handler->handle();
		}
		catch (ApiResponseException)
		{
			// Expected: the StubApiApplication::close() throws this to unwind the handler.
		}
		catch (\Throwable $e)
		{
			while (ob_get_level() > $baselineObLevel)
			{
				ob_end_clean();
			}
			throw $e;
		}

		// Capture the outermost of our two buffers as the response body; close everything
		// we opened so the ob level returns to the test's baseline exactly.
		$raw = '';
		while (ob_get_level() > $baselineObLevel + 1)
		{
			ob_end_clean();
		}
		if (ob_get_level() > $baselineObLevel)
		{
			$raw = (string) ob_get_clean();
		}

		$decoded = json_decode($raw, true);

		return [
			'status'  => http_response_code() ?: 200,
			'body'    => is_array($decoded) ? $decoded : null,
			'raw'     => $raw,
			'headers' => function_exists('xdebug_get_headers') ? xdebug_get_headers() : [],
		];
	}

	/**
	 * Dispatch through {@see \Akeeba\Panopticon\Controller\Api} so the full
	 * authentication + dispatch flow runs (used to test 401/route.not_found).
	 *
	 * @param   string               $handlerSuffix  e.g. 'V1\\Site\\GetList'
	 * @param   array<string,mixed>  $inputData
	 *
	 * @return  array{status:int, body:array|null}
	 */
	protected function dispatchApi(string $handlerSuffix, array $inputData = []): array
	{
		// AWF's Controller reads its Input from $container->input; replace it for this dispatch.
		$originalInput = $this->container->input;
		$newInput      = $this->makeInput(array_merge(['handler' => $handlerSuffix], $inputData));

		unset($this->container['input']);
		$this->container['input'] = fn() => $newInput;

		$controller = new \Akeeba\Panopticon\Controller\Api($this->container);

		http_response_code(200);

		$baselineObLevel = ob_get_level();
		ob_start();
		ob_start();

		try
		{
			$controller->execute('dispatch');
		}
		catch (ApiResponseException)
		{
			// Expected: StubApiApplication::close() throws this.
		}
		catch (\Throwable $e)
		{
			while (ob_get_level() > $baselineObLevel)
			{
				ob_end_clean();
			}
			unset($this->container['input']);
			$this->container['input'] = fn() => $originalInput;
			throw $e;
		}

		$raw = '';
		while (ob_get_level() > $baselineObLevel + 1)
		{
			ob_end_clean();
		}
		if (ob_get_level() > $baselineObLevel)
		{
			$raw = (string) ob_get_clean();
		}

		unset($this->container['input']);
		$this->container['input'] = fn() => $originalInput;

		$decoded = json_decode($raw, true);

		return [
			'status' => http_response_code() ?: 200,
			'body'   => is_array($decoded) ? $decoded : null,
			'raw'    => $raw,
		];
	}

	/**
	 * Update a site row's `config` JSON directly so its cmsType is the empty (UNKNOWN) backing
	 * value. {@see \Akeeba\Panopticon\Model\Site::check()} normalises empty/invalid cmsType to
	 * 'joomla', so tests that need the UNKNOWN state must bypass it via direct SQL.
	 */
	protected function forceUnknownCmsType(int $siteId): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->update($db->qn('#__sites'))
			->set($db->qn('config') . ' = ' . $db->q(json_encode(['cmsType' => ''])))
			->where($db->qn('id') . ' = ' . (int) $siteId);

		$db->setQuery($query)->execute();
	}

	/**
	 * Set an authenticated user on the container's userManager directly (skip token round-trip)
	 * for tests that only care about the post-auth behaviour of a handler.
	 *
	 * Also injects a synthetic token row with scopes = null (all scopes allowed) so that
	 * {@see \Akeeba\Panopticon\Controller\Api\AbstractApiHandler::requireScope()} does not
	 * reject the request with a "missing token context" 403. This keeps scope enforcement
	 * fail-closed in production while allowing handler-level integration tests to run
	 * without a real token round-trip.
	 */
	protected function loginAs(int $userId): void
	{
		$manager = $this->container->userManager;
		$user    = $manager->getUser($userId);

		$ref      = new \ReflectionObject($manager);
		$property = $ref->getProperty('currentUser');
		$property->setAccessible(true);
		$property->setValue($manager, $user);

		// Inject a synthetic token row with null scopes so requireScope() treats this
		// request as having all scopes. Real scope-restriction tests should call
		// injectTokenScopes() instead to set a restricted scope list.
		$this->container->apiCurrentToken = (object) [
			'id'      => 0,
			'user_id' => $userId,
			'scopes'  => null,
		];
	}

	/**
	 * Override the synthetic token row's scopes for tests that specifically exercise
	 * scope-restricted behaviour. Pass null to restore the "all scopes allowed" default.
	 *
	 * @param   string[]|null  $scopes  Scope value strings (e.g. ['sites:read']), or null for all.
	 */
	protected function injectTokenScopes(?array $scopes): void
	{
		$existing = $this->container->apiCurrentToken ?? (object) ['id' => 0, 'user_id' => 0, 'scopes' => null];

		$existing->scopes = $scopes === null ? null : json_encode($scopes);

		$this->container->apiCurrentToken = $existing;
	}
}
