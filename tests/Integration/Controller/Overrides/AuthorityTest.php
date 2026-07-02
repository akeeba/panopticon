<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Overrides;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Overrides;
use Akeeba\Panopticon\Exception\AccessDenied;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;

/**
 * Regression tests for the template-overrides view authority. Viewing a site's template overrides
 * must require the admin privilege on that specific site — previously a per-site view or editown
 * user could reach it, and the intended `read` privilege did not exist so the ACL gate was broken.
 *
 * @since  2.2.0
 */
class AuthorityTest extends AbstractControllerIntegrationTestCase
{
	public function testViewOnlyUserIsDenied(): void
	{
		$site   = $this->createSite();
		$viewer = $this->createUser(['parameters' => ['acl.panopticon.view' => 1]]);
		$this->loginAs((int) $viewer->getId());

		$this->expectException(AccessDenied::class);
		$this->dispatch(Overrides::class, 'browse', ['site_id' => (int) $site->getId()]);
	}

	public function testPrivilegelessUserIsDenied(): void
	{
		$site   = $this->createSite();
		$nobody = $this->createUser();
		$this->loginAs((int) $nobody->getId());

		$this->expectException(AccessDenied::class);
		$this->dispatch(Overrides::class, 'browse', ['site_id' => (int) $site->getId()]);
	}

	public function testAdminUserPassesTheAuthorityGate(): void
	{
		$site  = $this->createSite();
		$admin = $this->createUser(['parameters' => ['acl.panopticon.admin' => 1]]);
		$this->loginAs((int) $admin->getId());

		try
		{
			$this->dispatch(Overrides::class, 'browse', ['site_id' => (int) $site->getId()]);
		}
		catch (AccessDenied $e)
		{
			$this->fail('A per-site admin must pass the overrides authority gate.');
		}
		catch (\Throwable $e)
		{
			// The authority gate was cleared; any downstream (e.g. view rendering) error in the
			// headless test environment is not an authorization failure.
		}

		$this->addToAssertionCount(1);
	}
}
