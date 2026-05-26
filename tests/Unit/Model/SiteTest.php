<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Pure-unit tests for {@see Site::validateApiPayload()}.
 *
 * @since  1.4.0
 */
class SiteTest extends AbstractUnitTestCase
{
	public function testAddRequiresName(): void
	{
		$errors = Site::validateApiPayload(['url' => 'https://x.test/api'], true);

		$this->assertNotEmpty($errors);
		$this->assertStringContainsString('name', $errors[0]);
	}

	public function testAddRequiresUrl(): void
	{
		$errors = Site::validateApiPayload(['name' => 'X'], true);

		$this->assertNotEmpty($errors);
		$this->assertSame(['url is required and must be a string'], $errors);
	}

	public function testAddHappyPath(): void
	{
		$errors = Site::validateApiPayload([
			'name'    => 'OK',
			'url'     => 'https://ok.test/api',
			'enabled' => true,
			'config'  => ['config' => ['cmsType' => 'joomla']],
			'groups'  => [1, 2],
		], true);

		$this->assertSame([], $errors);
	}

	public function testModifyAllowsEmptyPayload(): void
	{
		$errors = Site::validateApiPayload([], false);

		$this->assertSame([], $errors);
	}

	public function testModifyRejectsEmptyNameWhenProvided(): void
	{
		$errors = Site::validateApiPayload(['name' => '   '], false);

		$this->assertCount(1, $errors);
		$this->assertStringContainsString('name', $errors[0]);
	}

	public function testRejectsInvalidConfigType(): void
	{
		$errors = Site::validateApiPayload([
			'name'   => 'X',
			'url'    => 'https://x.test/api',
			'config' => 'not-an-object',
		], true);

		$this->assertNotEmpty($errors);
		$this->assertContains('config must be an object', $errors);
	}

	public function testRejectsInvalidGroupsType(): void
	{
		$errors = Site::validateApiPayload([
			'name'   => 'X',
			'url'    => 'https://x.test/api',
			'groups' => 'nope',
		], true);

		$this->assertContains('groups must be an array of integers', $errors);
	}

	public function testRejectsNotesNonStringNonNull(): void
	{
		$errors = Site::validateApiPayload([
			'name'  => 'X',
			'url'   => 'https://x.test/api',
			'notes' => 12345,
		], true);

		$this->assertContains('notes must be a string or null', $errors);
	}
}
