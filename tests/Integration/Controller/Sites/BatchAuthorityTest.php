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
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * Regression tests for Sites::batch(). The task previously had neither an anti-CSRF token check nor
 * any per-site authority check, and the model rewrote each site's config.groups unconditionally —
 * letting any logged-in user assign a group (and thus privileges) to sites they do not administer.
 *
 * @since  2.2.0
 */
class BatchAuthorityTest extends AbstractControllerIntegrationTestCase
{
	private function siteGroups(int $siteId): array
	{
		/** @var Site $site */
		$site   = $this->container->mvcFactory->makeTempModel('Site');
		$site->findOrFail($siteId);
		$config = $site->config instanceof Registry ? $site->config : new Registry($site->config ?? '{}');

		return $config->get('config.groups', []) ?: [];
	}

	public function testBatchRejectsMissingCsrfToken(): void
	{
		$site = $this->createSite();
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid security token');

		// withCsrf: false → no token injected.
		$this->dispatch(
			Sites::class,
			'batch',
			['cid' => [(int) $site->getId()], 'groups' => [5]],
			false
		);
	}

	public function testNonAdminCannotBatchAssignGroupsToSite(): void
	{
		$site = $this->createSite();

		// A plain, non-super user with no admin privilege on the site.
		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		// The task filters out sites the user cannot administer; with none left it throws a 403.
		try
		{
			$this->dispatch(Sites::class, 'batch', ['cid' => [(int) $site->getId()], 'groups' => [5]]);
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(403, $e->getCode());
		}

		// Either way, the site's groups must be unchanged (empty).
		$this->assertSame([], $this->siteGroups((int) $site->getId()));
	}

	public function testSuperUserCanBatchAssignGroupsToSite(): void
	{
		$site  = $this->createSite();
		$super = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $super->getId());

		$this->dispatch(Sites::class, 'batch', ['cid' => [(int) $site->getId()], 'groups' => [5]]);

		$this->assertContains(5, $this->siteGroups((int) $site->getId()));
	}
}
