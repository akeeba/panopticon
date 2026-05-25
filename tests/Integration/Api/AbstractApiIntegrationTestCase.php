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

		parent::tearDown();
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

		ob_start();

		try
		{
			$handler->handle();
			$raw = (string) ob_get_clean();
		}
		catch (ApiResponseException)
		{
			$raw = (string) ob_get_clean();
		}
		catch (\Throwable $e)
		{
			ob_end_clean();
			throw $e;
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
		$input = $this->makeInput(array_merge(['handler' => $handlerSuffix], $inputData));

		$controller = new \Akeeba\Panopticon\Controller\Api('Api', $this->container, $input);

		http_response_code(200);
		ob_start();

		try
		{
			if ($controller->execute('dispatch') === false)
			{
				$raw = (string) ob_get_clean();
			}
			else
			{
				$raw = (string) ob_get_clean();
			}
		}
		catch (ApiResponseException)
		{
			$raw = (string) ob_get_clean();
		}
		catch (\Throwable $e)
		{
			ob_end_clean();
			throw $e;
		}

		$decoded = json_decode($raw, true);

		return [
			'status' => http_response_code() ?: 200,
			'body'   => is_array($decoded) ? $decoded : null,
			'raw'    => $raw,
		];
	}

	/**
	 * Set an authenticated user on the container's userManager directly (skip token round-trip)
	 * for tests that only care about the post-auth behaviour of a handler.
	 */
	protected function loginAs(int $userId): void
	{
		$manager = $this->container->userManager;
		$user    = $manager->getUser($userId);

		$ref      = new \ReflectionObject($manager);
		$property = $ref->getProperty('currentUser');
		$property->setAccessible(true);
		$property->setValue($manager, $user);
	}
}
