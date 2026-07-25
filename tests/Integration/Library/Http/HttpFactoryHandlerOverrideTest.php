<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Library\Http;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Http\HttpFactory;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use LogicException;
use ReflectionObject;

/**
 * Tests the refusal to build a client whose handler was supplied through the client options.
 *
 * A `handler` key in the client options wins over the handler stack {@see HttpFactory::makeClient()} assembles, so a
 * client built that way carries no forbidden IP ranges guard and silently reopens GHSA-6234-p3mh-7j3x. Nothing in the
 * application does this today; these tests exist so that the day something starts to, it fails immediately and loudly
 * rather than quietly shipping an unguarded client.
 *
 * @since 1.4.0
 */
class HttpFactoryHandlerOverrideTest extends AbstractIntegrationTestCase
{
	private function makeStack(): HandlerStack
	{
		return HandlerStack::create(new MockHandler([new Response(200, [], 'OK')]));
	}

	/**
	 * Read the options a Guzzle client was actually constructed with.
	 *
	 * Client::getConfig() is deprecated in Guzzle 7 and gone in 8, so go through the property directly.
	 */
	private function getClientConfig(Client $client): array
	{
		$property = (new ReflectionObject($client))->getProperty('config');
		$property->setAccessible(true);

		return $property->getValue($client);
	}

	/**
	 * The supported way of supplying a handler must keep working — the factory pushes the guard onto it.
	 */
	public function testStackParameterIsAccepted(): void
	{
		$client = $this->container->httpFactory->makeClient(
			stack: $this->makeStack(),
			cache: false,
			singleton: false
		);

		$this->assertSame(200, $client->get('http://93.184.216.34/')->getStatusCode());
	}

	public function testNoOptionsIsAccepted(): void
	{
		$this->assertInstanceOf(
			Client::class,
			$this->container->httpFactory->makeClient(cache: false, singleton: false)
		);
	}

	/**
	 * Unrelated client options must not trip the guard.
	 */
	public function testUnrelatedClientOptionsAreAccepted(): void
	{
		$this->assertInstanceOf(
			Client::class,
			$this->container->httpFactory->makeClient(
				clientOptions: ['timeout' => 10],
				cache: false,
				singleton: false
			)
		);
	}

	public function testHandlerInClientOptionsThrows(): void
	{
		$this->expectException(LogicException::class);

		$this->container->httpFactory->makeClient(
			clientOptions: ['handler' => $this->makeStack()],
			cache: false,
			singleton: false
		);
	}

	/**
	 * The advisory is named in the message so that whoever trips this can look up why it matters without having to
	 * reverse-engineer the intent from the code.
	 */
	public function testTheExceptionNamesTheAdvisory(): void
	{
		try
		{
			$this->container->httpFactory->makeClient(
				clientOptions: ['handler' => $this->makeStack()],
				cache: false,
				singleton: false
			);

			$this->fail('Expected a handler in the client options to be refused');
		}
		catch (LogicException $e)
		{
			$this->assertStringContainsString('GHSA-6234-p3mh-7j3x', $e->getMessage());
		}
	}

	/**
	 * The message has to point at the way out, otherwise the reader's cheapest fix is to delete the guard.
	 */
	public function testTheExceptionNamesTheAcknowledgementFlag(): void
	{
		try
		{
			$this->container->httpFactory->makeClient(
				clientOptions: ['handler' => $this->makeStack()],
				cache: false,
				singleton: false
			);

			$this->fail('Expected a handler in the client options to be refused');
		}
		catch (LogicException $e)
		{
			$this->assertStringContainsString(
				HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED,
				$e->getMessage()
			);
		}
	}

	/**
	 * A caller which explicitly acknowledges what it is doing gets the client it asked for.
	 */
	public function testAcknowledgementAllowsTheOverride(): void
	{
		$client = $this->container->httpFactory->makeClient(
			clientOptions: [
				'handler'                                         => $this->makeStack(),
				HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED => true,
			],
			cache: false,
			singleton: false
		);

		$this->assertInstanceOf(Client::class, $client);
	}

	/**
	 * Only a literal TRUE counts. A truthy value is most likely a mistake, and must not buy a pass.
	 */
	public function testTruthyAcknowledgementStillThrows(): void
	{
		$this->expectException(LogicException::class);

		$this->container->httpFactory->makeClient(
			clientOptions: [
				'handler'                                         => $this->makeStack(),
				HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED => 1,
			],
			cache: false,
			singleton: false
		);
	}

	/**
	 * The acknowledgement is our own invention. Guzzle treats unknown constructor options as default request options,
	 * so leaving it in would attach a junk option to every request the client makes.
	 */
	public function testAcknowledgementIsStrippedFromTheClientOptions(): void
	{
		$client = $this->container->httpFactory->makeClient(
			clientOptions: [
				'handler'                                         => $this->makeStack(),
				HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED => true,
			],
			cache: false,
			singleton: false
		);

		$this->assertArrayNotHasKey(
			HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED,
			$this->getClientConfig($client)
		);
	}

	/**
	 * The acknowledgement is stripped even when there is no handler to acknowledge, so that it can never leak through.
	 */
	public function testAcknowledgementIsStrippedEvenWithoutAHandler(): void
	{
		$client = $this->container->httpFactory->makeClient(
			clientOptions: [HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED => true],
			cache: false,
			singleton: false
		);

		$this->assertArrayNotHasKey(
			HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED,
			$this->getClientConfig($client)
		);
	}

	/**
	 * An acknowledged override really is unguarded. This is the behaviour the exception exists to prevent happening by
	 * accident, so it is worth pinning explicitly: the escape hatch is a real escape hatch, not a no-op.
	 */
	public function testAnAcknowledgedOverrideIsGenuinelyUnguarded(): void
	{
		$original = $this->container->appConfig->get('forbidden_ip_ranges', []);

		$this->container->appConfig->set('forbidden_ip_ranges', ['127.0.0.0/8']);

		try
		{
			$client = $this->container->httpFactory->makeClient(
				clientOptions: [
					'handler'                                         => $this->makeStack(),
					HttpFactory::OPTION_HANDLER_OVERRIDE_ACKNOWLEDGED => true,
				],
				cache: false,
				singleton: false
			);

			$this->assertSame(200, $client->get('http://127.0.0.1/')->getStatusCode());
		}
		finally
		{
			$this->container->appConfig->set('forbidden_ip_ranges', $original);
		}
	}

	/**
	 * The refusal must happen before the client is memoised, otherwise a rejected call would still poison the cache for
	 * a later, legitimate one with the same signature.
	 */
	public function testARefusedCallDoesNotPoisonTheSingletonCache(): void
	{
		$stack = $this->makeStack();

		try
		{
			$this->container->httpFactory->makeClient(
				clientOptions: ['handler' => $stack],
				cache: false,
				singleton: true
			);

			$this->fail('Expected a handler in the client options to be refused');
		}
		catch (LogicException)
		{
			// Expected.
		}

		$client = $this->container->httpFactory->makeClient(
			stack: $stack,
			cache: false,
			singleton: true
		);

		$this->assertSame(200, $client->get('http://93.184.216.34/')->getStatusCode());
	}
}
