<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Task\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for ResponseLoggerTrait — exercises sanitizeHeaderValues(), sanitizeUrl(),
 * isBinaryContentType(), and formatResponseLog() via an anonymous class harness that uses
 * both ResponseLoggerTrait and JsonSanitizerTrait (the latter is needed by formatResponseLog).
 *
 * @since 1.4.0
 */
class ResponseLoggerTraitTest extends AbstractUnitTestCase
{
	private function makeSut(): object
	{
		return new class {
			use \Akeeba\Panopticon\Task\Trait\ResponseLoggerTrait;
			use \Akeeba\Panopticon\Task\Trait\JsonSanitizerTrait;

			public function sanitizeHeaders(array $headers): array
			{
				return $this->sanitizeHeaderValues($headers);
			}

			public function sanitizeUrlPublic(string $url): string
			{
				return $this->sanitizeUrl($url);
			}

			public function isBinary(string $contentType): bool
			{
				return $this->isBinaryContentType($contentType);
			}

			public function formatResponseLogPublic(\Psr\Http\Message\ResponseInterface $response, ?string $preReadBody = null): array
			{
				return $this->formatResponseLog($response, $preReadBody);
			}
		};
	}

	// ── sanitizeHeaderValues ─────────────────────────────────────────────────

	public function testAuthorizationHeaderIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['Authorization' => 'Bearer secret-token']);

		$this->assertSame(['[REDACTED]'], $result['Authorization']);
	}

	public function testAuthorizationHeaderLowercaseIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['authorization' => 'Bearer secret-token']);

		$this->assertSame(['[REDACTED]'], $result['authorization']);
	}

	public function testXJoomlaTokenIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['X-Joomla-Token' => 'abc123']);

		$this->assertSame(['[REDACTED]'], $result['X-Joomla-Token']);
	}

	public function testXJoomlaTokenLowercaseIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['x-joomla-token' => 'abc123']);

		$this->assertSame(['[REDACTED]'], $result['x-joomla-token']);
	}

	public function testXPanopticonTokenIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['X-Panopticon-Token' => 'secret']);

		$this->assertSame(['[REDACTED]'], $result['X-Panopticon-Token']);
	}

	public function testXPanopticonTokenMixedCaseIsRedacted(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['x-PANOPTICON-token' => 'secret']);

		$this->assertSame(['[REDACTED]'], $result['x-PANOPTICON-token']);
	}

	public function testNonSensitiveHeaderIsLeftIntact(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['Content-Type' => 'application/json']);

		$this->assertSame(['application/json'], $result['Content-Type']);
	}

	public function testScalarHeaderValueIsNormalisedToArray(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['Accept' => 'text/html']);

		$this->assertIsArray($result['Accept']);
		$this->assertSame(['text/html'], $result['Accept']);
	}

	public function testArrayHeaderValueIsPreservedAsArray(): void
	{
		$sut = $this->makeSut();
		$result = $sut->sanitizeHeaders(['Accept' => ['text/html', 'application/json']]);

		$this->assertSame(['text/html', 'application/json'], $result['Accept']);
	}

	// ── sanitizeUrl ──────────────────────────────────────────────────────────

	public function testAkeebaAuthParamIsRedacted(): void
	{
		$sut = $this->makeSut();
		$url = 'https://example.com/api?_akeebaAuth=supersecret&format=json';

		$result = $sut->sanitizeUrlPublic($url);

		$this->assertStringContainsString('_akeebaAuth=%5BREDACTED%5D', $result);
		$this->assertStringContainsString('format=json', $result);
		$this->assertStringNotContainsString('supersecret', $result);
	}

	public function testUrlWithNoQueryStringIsUnchanged(): void
	{
		$sut = $this->makeSut();
		$url = 'https://example.com/api/endpoint';

		$this->assertSame($url, $sut->sanitizeUrlPublic($url));
	}

	public function testUrlWithOtherParamsOnlyIsUnchanged(): void
	{
		$sut = $this->makeSut();
		$url = 'https://example.com/api?format=json&version=2';

		$result = $sut->sanitizeUrlPublic($url);

		$this->assertStringContainsString('format=json', $result);
		$this->assertStringContainsString('version=2', $result);
	}

	public function testPathIsPreservedAfterRedaction(): void
	{
		$sut = $this->makeSut();
		$url = 'https://example.com/index.php?_akeebaAuth=token';

		$result = $sut->sanitizeUrlPublic($url);

		$this->assertStringStartsWith('https://example.com/index.php?', $result);
	}

	// ── isBinaryContentType ──────────────────────────────────────────────────

	public function testApplicationZipIsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('application/zip'));
	}

	public function testApplicationOctetStreamIsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('application/octet-stream'));
	}

	public function testImagePngIsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('image/png'));
	}

	public function testAudioMpegIsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('audio/mpeg'));
	}

	public function testVideoMp4IsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('video/mp4'));
	}

	public function testBinaryTypeWithCharsetSuffixIsBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertTrue($sut->isBinary('application/zip; charset=utf-8'));
	}

	public function testApplicationJsonIsNotBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertFalse($sut->isBinary('application/json'));
	}

	public function testTextHtmlIsNotBinary(): void
	{
		$sut = $this->makeSut();

		$this->assertFalse($sut->isBinary('text/html'));
	}

	// ── formatResponseLog (optional — uses GuzzleHttp\Psr7\Response) ─────────

	public function testFormatResponseLogReturnsExpectedKeys(): void
	{
		$sut = $this->makeSut();
		$response = new Response(200, ['Content-Type' => 'application/json'], '{"x":1}');

		$log = $sut->formatResponseLogPublic($response);

		$this->assertArrayHasKey('status', $log);
		$this->assertArrayHasKey('content_type', $log);
		$this->assertArrayHasKey('headers', $log);
		$this->assertArrayHasKey('response_body', $log);
	}

	public function testFormatResponseLogStatusCode(): void
	{
		$sut = $this->makeSut();
		$response = new Response(200, ['Content-Type' => 'application/json'], '{"x":1}');

		$log = $sut->formatResponseLogPublic($response);

		$this->assertSame(200, $log['status']);
	}

	public function testFormatResponseLogContentType(): void
	{
		$sut = $this->makeSut();
		$response = new Response(200, ['Content-Type' => 'application/json'], '{"x":1}');

		$log = $sut->formatResponseLogPublic($response);

		$this->assertSame('application/json', $log['content_type']);
	}

	public function testFormatResponseLogBodyIsReturned(): void
	{
		$sut = $this->makeSut();
		$response = new Response(200, ['Content-Type' => 'application/json'], '{"x":1}');

		$log = $sut->formatResponseLogPublic($response);

		$this->assertSame('{"x":1}', $log['response_body']);
	}

	public function testFormatResponseLogRedactsAuthHeaders(): void
	{
		$sut = $this->makeSut();
		$response = new Response(
			200,
			[
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer secret',
			],
			'{"x":1}'
		);

		$log = $sut->formatResponseLogPublic($response);

		$this->assertSame(['[REDACTED]'], $log['headers']['Authorization']);
	}
}
