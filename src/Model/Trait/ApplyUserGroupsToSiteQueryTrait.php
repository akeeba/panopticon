<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Awf\Database\Query;

trait ApplyUserGroupsToSiteQueryTrait
{
	/**
	 * Limit display of sites only to those the user is allowed to view / access.
	 *
	 * This is common code, used by models which query the `#__sites` table.
	 *
	 * The filtering has no effect when the model is used under the CLI, or when the user is a super user, or has the
	 * global view privilege.
	 *
	 * @param   Query  $query
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	private function applyUserGroupsToQuery(Query $query): void
	{
		if (defined('AKEEBA_CLI'))
		{
			return;
		}

		// Get the user, so we can apply per group privilege checks
		$user = $this->container->userManager->getUser();

		// If the user is a Super User, or has a global view privilege, we have no checks to make
		if ($user->getPrivilege('panopticon.super') || $user->getPrivilege('panopticon.view'))
		{
			return;
		}

		// In any other case, get the list of groups for the user and limit listing sites visible to these groups
		$groupPrivileges = $user->getGroupPrivileges();

		if (empty($groupPrivileges))
		{
			// There are no groups the user belongs to. Therefore, the user can only see their own sites.
			$query->where($query->quoteName('created_by') . ' = ' . $query->quote($user->getId()));

			return;
		}

		// Filter out groups with read privileges
		$groupPrivileges = array_filter(
			$groupPrivileges,
			fn($privileges) => in_array('panopticon.view', $privileges)
		);

		if (empty($groupPrivileges))
		{
			// There are no groups with read privileges the user belongs to. Therefore, the user can only see their own sites.
			$query->where($query->quoteName('created_by') . ' = ' . $query->quote($user->getId()));

			return;
		}

		// We allow the user to view their own sites
		$clauses = [
			$query->quoteName('created_by') . ' = ' . $query->quote($user->getId()),
		];

		// Basically: a bunch of JSON_CONTAINS(`config`, '1', '$.config.groups') with ORs between them
		foreach (array_keys($groupPrivileges) as $gid)
		{
			$clauses[] = $query->jsonContains(
				$query->quoteName('config'), $query->quote('"' . (int) $gid . '"'), $query->quote('$.config.groups')
			);
			$clauses[] = $query->jsonContains(
				$query->quoteName('config'), $query->quote((int) $gid), $query->quote('$.config.groups')
			);
		}

		$query->extendWhere('AND', $clauses, 'OR');
	}

}