<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Sites;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;

/**
 * Regression tests for the `sites` ACL map task keys. The keys were camelCase, but hasAccess()
 * lower-cases the incoming task before the lookup, so they never matched and every finer view/run
 * grant silently collapsed to the '*' => ['admin'] default. After lower-casing the keys (and
 * correcting the non-existent `read` privilege to the real `view` privilege), the intended per-task
 * granularity is restored:
 *   - the refresh tasks require only `view`
 *   - the schedule / fix / clear tasks require `run`
 *   - the extension/plugin scheduling tasks (site identified by site_id, not id) pass the ACL for
 *     any logged-in user and are authorised in-controller instead.
 *
 * The checks run against GLOBAL privileges, which authorise() honours for any site id, so no group
 * wiring or real site is needed.
 *
 * @since  2.2.0
 */
class AclTaskKeyGranularityTest extends AbstractControllerIntegrationTestCase
{
	public function testViewUserMayRefreshSiteInformation(): void
	{
		$reader = $this->createUser(['parameters' => ['acl.panopticon.view' => 1]]);
		$this->loginAs((int) $reader->getId());

		// Must NOT throw: refreshSiteInformation requires only `view` once the key matches.
		$this->runAclCheck(Sites::class, 'refreshSiteInformation', ['id' => 1]);

		$this->addToAssertionCount(1);
	}

	public function testViewUserMayNotScheduleJoomlaUpdate(): void
	{
		$reader = $this->createUser(['parameters' => ['acl.panopticon.view' => 1]]);
		$this->loginAs((int) $reader->getId());

		// scheduleJoomlaUpdate requires `run`; a read-only user must be denied.
		$this->expectException(AccessDenied::class);
		$this->runAclCheck(Sites::class, 'scheduleJoomlaUpdate', ['id' => 1]);
	}

	public function testRunUserMayScheduleJoomlaUpdate(): void
	{
		$runner = $this->createUser(['parameters' => ['acl.panopticon.run' => 1]]);
		$this->loginAs((int) $runner->getId());

		$this->runAclCheck(Sites::class, 'scheduleJoomlaUpdate', ['id' => 1]);

		$this->addToAssertionCount(1);
	}

	public function testPrivilegelessUserIsDeniedRefresh(): void
	{
		$nobody = $this->createUser();
		$this->loginAs((int) $nobody->getId());

		$this->expectException(AccessDenied::class);
		$this->runAclCheck(Sites::class, 'refreshSiteInformation', ['id' => 1]);
	}

	public function testExtensionScheduleTaskPassesAclForAnyLoggedInUser(): void
	{
		// The ACL for scheduleExtensionUpdate is '*' (authority is enforced in-controller against the
		// real site_id), so a plain logged-in user passes the ACL gate.
		$nobody = $this->createUser();
		$this->loginAs((int) $nobody->getId());

		$this->runAclCheck(Sites::class, 'scheduleExtensionUpdate', ['id' => 1, 'site_id' => 1]);

		$this->addToAssertionCount(1);
	}
}
