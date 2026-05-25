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
use Awf\Document\Json as JsonDocument;

/**
 * Minimal non-exiting Application stub for API integration tests.
 *
 * The real {@see \Awf\Application\Application::close()} calls `exit()`, which is incompatible
 * with PHPUnit. This stub throws {@see ApiResponseException} instead, letting tests catch the
 * unwind, flush the output buffer, and assert against the captured JSON.
 *
 * We deliberately do NOT extend the real Application class — its constructor starts a session,
 * loads templates and the language file. Tests don't need any of that. We implement just the
 * methods the API code path touches.
 *
 * @since  1.4.0
 */
class StubApiApplication
{
	public function __construct(public readonly Container $container) {}

	public function close($code = 0): void
	{
		throw new ApiResponseException((int) $code);
	}

	public function getDocument(): object
	{
		// API code only ever calls $doc->setUseHashes(false) when the doc is a Json doc.
		// Returning an anonymous stub keeps the call site happy without bringing in the
		// full document machinery.
		return new class
		{
			public function setUseHashes(bool $value): void {}
		};
	}

	public function getContainer(): Container
	{
		return $this->container;
	}

	public function getName(): string
	{
		return 'Panopticon';
	}

	public function getTemplate(): string
	{
		return 'default';
	}

	// Swallow any other call so we don't have to enumerate every method the framework might invoke.
	public function __call(string $name, array $args): mixed
	{
		return null;
	}
}
