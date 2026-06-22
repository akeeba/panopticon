<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Mcp\ToolRegistry;
use Akeeba\Panopticon\Model\Groups;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Verifies the MCP tool access-control layers: token scope, Super-User restriction, the global
 * application kill-switch, and the per-group "ALLOW wins" union rule.
 *
 * @since  2.2.0
 */
class McpToolAccessTest extends AbstractApiIntegrationTestCase
{
	private function toolNames(): array
	{
		return array_values(
			array_map(
				fn($tool) => $tool->getName(),
				(new ToolRegistry($this->container))->getAvailableTools()
			)
		);
	}

	/**
	 * Create a user group with an optional list of disallowed MCP tools and return its ID.
	 */
	private function makeGroup(array $disallowedTools = []): int
	{
		$group = new Groups($this->container);
		$group->reset();
		$group->title = 'MCP Test Group ' . bin2hex(random_bytes(3));
		$group->setPrivileges([]);
		$group->setMcpDisallowedTools($disallowedTools);
		$group->save();

		return (int) $group->getId();
	}

	public function testSuperUserWithAllScopesSeesEveryTool(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());

		$names = $this->toolNames();

		$this->assertContains('list_sites', $names);
		$this->assertContains('get_stats', $names, 'Super User should see Super-User-only tools');
		$this->assertContains('get_sysconfig', $names);
		$this->assertCount(
			count((new ToolRegistry($this->container))->getAllTools()),
			$names,
			'A Super User with all scopes and no restrictions should see every tool'
		);
	}

	public function testNonSuperUserCannotSeeSuperUserOnlyTools(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$names = $this->toolNames();

		$this->assertContains('list_sites', $names);
		$this->assertNotContains('get_stats', $names);
		$this->assertNotContains('get_sysconfig', $names);
		$this->assertNotContains('get_selfupdate_info', $names);
	}

	public function testTokenScopeRestrictsTools(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());
		$this->injectTokenScopes(['sites:read']);

		$names = $this->toolNames();

		$this->assertContains('list_sites', $names, 'sites:read grants list_sites');
		$this->assertContains('get_site', $names);
		$this->assertNotContains('list_site_extensions', $names, 'sites:extensions scope is absent');
		$this->assertNotContains('schedule_cms_update', $names, 'sites:cms-update scope is absent');
		$this->assertNotContains('list_tasks', $names, 'tasks:read scope is absent');
	}

	public function testGlobalDisallowIsAbsoluteKillSwitch(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => true]]);
		$this->loginAs((int) $user->getId());

		$this->container->appConfig->set('mcp_disallowed_tools', 'get_stats,refresh_site');

		$names = $this->toolNames();

		$this->assertNotContains('get_stats', $names);
		$this->assertNotContains('refresh_site', $names);
		$this->assertContains('list_sites', $names);

		$this->container->appConfig->set('mcp_disallowed_tools', '');
	}

	public function testPerGroupAllowWinsWhenOneGroupAllows(): void
	{
		$groupDeny  = $this->makeGroup(['get_site']);
		$groupAllow = $this->makeGroup([]); // imposes no restriction

		$user = $this->createUser([
			'parameters' => ['usergroups' => [$groupDeny, $groupAllow]],
		]);
		$this->loginAs((int) $user->getId());

		$this->assertContains(
			'get_site',
			$this->toolNames(),
			'When one group allows a tool, access must be granted even if another group denies it'
		);
	}

	public function testPerGroupDenyWinsOnlyWhenEveryGroupDenies(): void
	{
		$groupDenyA = $this->makeGroup(['get_site']);
		$groupDenyB = $this->makeGroup(['get_site']);

		$user = $this->createUser([
			'parameters' => ['usergroups' => [$groupDenyA, $groupDenyB]],
		]);
		$this->loginAs((int) $user->getId());

		$names = $this->toolNames();

		$this->assertNotContains(
			'get_site',
			$names,
			'A tool is only blocked when every one of the user\'s groups disables it'
		);
		// A tool that no group denies remains available.
		$this->assertContains('list_sites', $names);
	}

	public function testUserWithNoGroupsHasNoGroupRestrictions(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$this->assertContains('get_site', $this->toolNames());
	}
}
