<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Groups;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;

/**
 * Tests the badge colour added to groups in gh-1023.
 *
 * These tests double as a regression guard for the `colour` column's schema migration. The migration's
 * `<condition type="missing" />` must name the column in the `value` ATTRIBUTE — AWF's
 * Awf\Database\Installer::conditionMet() reads the attribute, not the node's text. Spelled the wrong
 * way, the condition silently degrades to "does the table exist?", the ALTER TABLE never runs on an
 * existing installation, and the column is missing everywhere. If that regresses, every test here dies
 * with an "unknown column" error.
 *
 * @since 2.2.1
 */
class GroupsColourTest extends AbstractIntegrationTestCase
{
	public function testAValidColourIsPersistedAndReadBack(): void
	{
		$group = new Groups($this->container);
		$group->bind(['title' => 'Colour Test', 'colour' => '#0d6efd']);
		$group->save();

		$reloaded = new Groups($this->container);
		$reloaded->findOrFail($group->getId());

		$this->assertSame('#0d6efd', $reloaded->colour);
	}

	public function testAColourIsNormalisedOnSave(): void
	{
		$group = new Groups($this->container);
		$group->bind(['title' => 'Shorthand Colour', 'colour' => '#ABC']);
		$group->save();

		$reloaded = new Groups($this->container);
		$reloaded->findOrFail($group->getId());

		$this->assertSame('#aabbcc', $reloaded->colour);
	}

	public function testAnInvalidColourIsPersistedAsNull(): void
	{
		$group = new Groups($this->container);
		$group->bind(['title' => 'Junk Colour', 'colour' => 'red; background-image: url(evil)']);
		$group->save();

		$reloaded = new Groups($this->container);
		$reloaded->findOrFail($group->getId());

		$this->assertNull($reloaded->colour);
	}

	public function testGetGroupColoursReturnsTheSameKeysAsGetGroupMap(): void
	{
		/** @var Groups $model */
		$model = $this->container->mvcFactory->makeTempModel('groups');

		$this->assertSame(
			array_keys($model->getGroupMap(false)),
			array_keys($model->getGroupColours(false))
		);
	}
}
