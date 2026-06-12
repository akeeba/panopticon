<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Version;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\UpdateActionResolver;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Unit tests for UpdateActionResolver::resolve().
 *
 * Covers the full decision matrix: none/email pass-through, major → update, minor same/different
 * major, patch same/different minor, default fall-through to patch behaviour, and two-part versions.
 *
 * @since 1.4.0
 */
class UpdateActionResolverTest extends AbstractUnitTestCase
{
	public static function provideResolve(): array
	{
		return [
			// none / email — pass-through regardless of versions
			'none returns none (same versions)'        => ['none', '4.4.0', '4.4.0', 'none'],
			'none returns none (different versions)'   => ['none', '4.4.0', '5.0.0', 'none'],
			'email returns email (same versions)'      => ['email', '4.4.0', '4.4.0', 'email'],
			'email returns email (different versions)' => ['email', '4.4.0', '5.0.0', 'email'],

			// major — always update
			'major same-major update'                  => ['major', '4.4.0', '4.4.3', 'update'],
			'major cross-major update'                 => ['major', '4.4.0', '6.0.0', 'update'],

			// minor — update only when major matches
			'minor same major → update'                => ['minor', '4.1.0', '4.4.2', 'update'],
			'minor different major → email'            => ['minor', '4.4.0', '5.0.0', 'email'],

			// patch — update only when major.minor matches
			'patch same major.minor → update'          => ['patch', '4.4.0', '4.4.3', 'update'],
			'patch different minor → email'            => ['patch', '4.4.0', '4.5.0', 'email'],
			'patch different major → email'            => ['patch', '4.4.0', '5.0.0', 'email'],

			// default / unknown action falls through to patch behaviour
			'unknown action, same major.minor → update' => ['', '4.4.0', '4.4.3', 'update'],
			'unknown action, different minor → email'   => ['', '4.4.0', '4.5.0', 'email'],
			'unknown action, different major → email'   => ['', '4.4.0', '5.0.0', 'email'],

			// two-part versions (no patch segment)
			'patch two-part same minor → update'       => ['patch', '4.4', '4.4', 'update'],
			'patch two-part different minor → email'   => ['patch', '4.4', '4.5', 'email'],
			'minor two-part same major → update'       => ['minor', '4.4', '4.5', 'update'],
			'minor two-part different major → email'   => ['minor', '4.4', '5.0', 'email'],
		];
	}

	/**
	 * @dataProvider provideResolve
	 */
	public function testResolve(
		string $updateAction,
		string $currentVersion,
		string $latestVersion,
		string $expected
	): void
	{
		$this->assertSame(
			$expected,
			UpdateActionResolver::resolve($updateAction, $currentVersion, $latestVersion)
		);
	}
}
