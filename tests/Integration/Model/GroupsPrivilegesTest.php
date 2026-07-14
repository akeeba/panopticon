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
 * Tests that a group's `privileges` column is always read back as a list, whatever shape it has on disk.
 *
 * The column could legitimately contain a JSON *object* instead of a JSON array: the default "Users" group used to be
 * seeded with `{}`, and Groups::setPrivileges() used to emit an object whenever it filtered out a privilege which was
 * not the last one in the list, because array_filter() preserves keys. Every consumer then blew up — the group edit
 * page with a TypeError out of getPrivileges(), and the ACL checks with in_array() being handed an stdClass.
 *
 * The last test here doubles as a regression guard for the schema migration which normalises those rows.
 *
 * @since 2.2.1
 */
class GroupsPrivilegesTest extends AbstractIntegrationTestCase
{
	public function testAnEmptyJsonObjectIsReadBackAsAnEmptyList(): void
	{
		$group = $this->makeGroupWithRawPrivileges('Legacy Empty Object', '{}');

		$this->assertSame([], $group->getPrivileges());
	}

	public function testAJsonObjectWithGapsInItsKeysIsReadBackAsAList(): void
	{
		$group = $this->makeGroupWithRawPrivileges(
			'Legacy Sparse Object',
			'{"0": "panopticon.view", "2": "panopticon.admin"}'
		);

		$this->assertSame(['panopticon.view', 'panopticon.admin'], $group->getPrivileges());
	}

	public function testANullPrivilegesColumnIsReadBackAsAnEmptyList(): void
	{
		$group = $this->makeGroupWithRawPrivileges('No Privileges At All', null);

		$this->assertSame([], $group->getPrivileges());
	}

	public function testAJsonArrayIsReadBackVerbatim(): void
	{
		$group = $this->makeGroupWithRawPrivileges(
			'Modern Group', '["panopticon.view", "panopticon.admin"]'
		);

		$this->assertSame(['panopticon.view', 'panopticon.admin'], $group->getPrivileges());
	}

	/**
	 * Dropping a privilege which is not the last one in the list must still store a JSON array.
	 */
	public function testSetPrivilegesStoresAJsonArrayEvenWhenItFiltersOutAPrivilege(): void
	{
		$group = new Groups($this->container);
		$group->bind(['title' => 'Filtered Privileges']);
		$group->setPrivileges(['panopticon.view', 'panopticon.super', 'panopticon.admin']);
		$group->save();

		$reloaded = new Groups($this->container);
		$reloaded->findOrFail($group->getId());

		$this->assertSame(['panopticon.view', 'panopticon.admin'], $reloaded->getPrivileges());
		$this->assertSame('[', substr(trim((string) $reloaded->privileges), 0, 1));
	}

	/**
	 * Guards the schema migration: after the schema is applied, no group may be left holding a JSON object.
	 */
	public function testNoGroupIsLeftWithANonArrayPrivilegesColumn(): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__groups'))
			->where(
				'(' . $db->quoteName('privileges') . ' IS NULL OR TRIM(CAST('
				. $db->quoteName('privileges') . ' AS CHAR)) NOT LIKE ' . $db->quote('[%') . ')'
			);

		$this->assertEquals(0, $db->setQuery($query)->loadResult());
	}

	private function makeGroupWithRawPrivileges(string $title, ?string $privileges): Groups
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->insert($db->quoteName('#__groups'))
			->columns([$db->quoteName('title'), $db->quoteName('privileges')])
			->values($db->quote($title) . ', ' . ($privileges === null ? 'NULL' : $db->quote($privileges)));

		$db->setQuery($query)->execute();

		// Read the ID before touching the model: the first DataModel instantiation in the process runs its own field
		// discovery query, and that resets the connection's insert ID.
		$id = $db->insertid();

		$group = new Groups($this->container);
		$group->findOrFail($id);

		return $group;
	}
}
