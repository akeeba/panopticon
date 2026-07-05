<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Unit tests for BootstrapUtilities::generateRandomSecret(), the shared installation-secret generator used by both
 * applySecret() and the config:create CLI command (see gh-1010).
 *
 * @since  2.2.1
 */
class GenerateRandomSecretTest extends AbstractUnitTestCase
{
	public function testDefaultLengthIs64(): void
	{
		$this->assertSame(64, strlen(BootstrapUtilities::generateRandomSecret()));
	}

	public function testRespectsRequestedLength(): void
	{
		foreach ([1, 16, 32, 128] as $length)
		{
			$this->assertSame(
				$length,
				strlen(BootstrapUtilities::generateRandomSecret($length)),
				sprintf('Requested length %d must be honoured.', $length)
			);
		}
	}

	public function testOnlyContainsUrlSafeAlphanumericCharacters(): void
	{
		$secret = BootstrapUtilities::generateRandomSecret(256);

		$this->assertMatchesRegularExpression(
			'/^[a-zA-Z0-9]+$/',
			$secret,
			'The secret must only contain a-z, A-Z, and 0-9 so it is safe to embed in config.php and in URLs.'
		);
	}

	public function testIsNonDeterministic(): void
	{
		$this->assertNotSame(
			BootstrapUtilities::generateRandomSecret(64),
			BootstrapUtilities::generateRandomSecret(64),
			'Two generated secrets must not be identical.'
		);
	}
}
