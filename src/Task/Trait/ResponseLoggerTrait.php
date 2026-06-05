<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

defined('AKEEBA') || die;

use Psr\Http\Message\ResponseInterface;

/**
 * Provides structured HTTP response/request logging helpers.
 *
 * @see JsonSanitizerTrait Required by formatResponseLog for body sanitisation.
 */
trait ResponseLoggerTrait
{
	/**
	 * Build a structured log context array from a PSR-7 HTTP response.
	 *
	 * When $preReadBody is supplied the stream is not read again; this avoids double-reading a non-seekable stream when
	 * the caller has already consumed the body for its own use.
	 *
	 * Binary responses (zip, octet-stream, images, …) are represented by a BLOB sentinel string instead of body text.
	 *
	 * Auth-related response headers (Authorization, X-Joomla-Token, X-Panopticon-Token) are redacted.
	 *
	 * @param   ResponseInterface  $response      The HTTP response.
	 * @param   string|null        $preReadBody   Optional pre-read raw body string.
	 *
	 * @return  array{status: int, content_type: string, headers: array, response_body: string}
	 */
	private function formatResponseLog(ResponseInterface $response, ?string $preReadBody = null): array
	{
		$contentType = $response->getHeaderLine('Content-Type');
		$status      = $response->getStatusCode();
		$headers     = $this->sanitizeHeaderValues($response->getHeaders());

		if ($this->isBinaryContentType($contentType))
		{
			if ($preReadBody !== null)
			{
				$size = strlen($preReadBody);
			}
			else
			{
				$body = $response->getBody();
				$size = $body->getSize();

				if ($size === null)
				{
					$size = strlen($body->getContents());
				}
			}

			$responseBody = sprintf('(BLOB of %d bytes)', $size);
		}
		else
		{
			$responseBody = $preReadBody ?? $response->getBody()->getContents();

			if (method_exists($this, 'sanitizeJson'))
			{
				$responseBody = $this->sanitizeJson($responseBody);
			}
		}

		return [
			'status'        => $status,
			'content_type'  => $contentType,
			'headers'       => $headers,
			'response_body' => $responseBody,
		];
	}

	/**
	 * Build a structured log context array for an outgoing HTTP request.
	 *
	 * Auth-related headers and the _akeebaAuth URL query parameter are redacted.
	 *
	 * @param   string            $url      The full request URL.
	 * @param   array             $headers  Request headers as ['Name' => 'value'] or ['Name' => ['value']].
	 * @param   mixed             $body     Optional request body (array for form data, string for raw body).
	 *
	 * @return  array{request_url: string, request_headers: array, request_body: mixed}
	 */
	private function formatRequestLog(string $url, array $headers = [], mixed $body = null): array
	{
		return [
			'request_url'     => $this->sanitizeUrl($url),
			'request_headers' => $this->sanitizeHeaderValues($headers),
			'request_body'    => $body,
		];
	}

	/**
	 * Returns true when the given Content-Type indicates a binary (non-text) payload.
	 */
	private function isBinaryContentType(string $contentType): bool
	{
		$mime = strtolower(trim(explode(';', $contentType)[0]));

		$binaryTypes = [
			'application/octet-stream',
			'application/zip',
			'application/x-zip',
			'application/x-zip-compressed',
			'application/x-gzip',
			'application/gzip',
			'application/x-tar',
			'application/x-bzip',
			'application/x-bzip2',
		];

		if (in_array($mime, $binaryTypes))
		{
			return true;
		}

		return str_starts_with($mime, 'image/')
			|| str_starts_with($mime, 'audio/')
			|| str_starts_with($mime, 'video/');
	}

	/**
	 * Returns a copy of $headers with sensitive auth header values replaced by [REDACTED].
	 *
	 * @param   array  $headers  Headers as ['Name' => string|string[]].
	 *
	 * @return  array
	 */
	private function sanitizeHeaderValues(array $headers): array
	{
		$sensitive = ['authorization', 'x-joomla-token', 'x-panopticon-token'];
		$result    = [];

		foreach ($headers as $name => $values)
		{
			if (in_array(strtolower($name), $sensitive, true))
			{
				$result[$name] = ['[REDACTED]'];
			}
			else
			{
				$result[$name] = is_array($values) ? $values : [$values];
			}
		}

		return $result;
	}

	/**
	 * Returns the URL with sensitive query parameters redacted.
	 *
	 * Currently redacts: _akeebaAuth.
	 */
	private function sanitizeUrl(string $url): string
	{
		$parsed = parse_url($url);

		if (empty($parsed['query']))
		{
			return $url;
		}

		parse_str($parsed['query'], $params);

		foreach (['_akeebaAuth'] as $param)
		{
			if (isset($params[$param]))
			{
				$params[$param] = '[REDACTED]';
			}
		}

		$parsed['query'] = http_build_query($params);

		return $this->buildUrlFromParts($parsed);
	}

	private function buildUrlFromParts(array $parts): string
	{
		$url = '';

		if (!empty($parts['scheme']))
		{
			$url .= $parts['scheme'] . '://';
		}

		if (!empty($parts['host']))
		{
			$url .= $parts['host'];
		}

		if (!empty($parts['port']))
		{
			$url .= ':' . $parts['port'];
		}

		if (!empty($parts['path']))
		{
			$url .= $parts['path'];
		}

		if (!empty($parts['query']))
		{
			$url .= '?' . $parts['query'];
		}

		if (!empty($parts['fragment']))
		{
			$url .= '#' . $parts['fragment'];
		}

		return $url;
	}
}
