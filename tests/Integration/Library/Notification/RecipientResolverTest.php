<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Library\Notification;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Notification\RecipientResolver;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Registry\Registry;

/**
 * Tests for RecipientResolver::resolveUserIds() — the shared recipient-resolution logic used by
 * both Web Push and any onNotificationSend plugin.
 *
 * @since 2.3.0
 */
class RecipientResolverTest extends AbstractIntegrationTestCase
{
	public function testRecipientIdOverrideTakesPrecedence(): void
	{
		$data = new Registry();
		$data->set('recipient_id', 42);
		// A permission that would otherwise match nobody in the DB, to prove it's ignored.
		$data->set('permissions', ['panopticon.nonexistent']);

		$userIds = (new RecipientResolver())->resolveUserIds($data, null);

		$this->assertSame([42], $userIds);
	}

	public function testResolvesUsersByPermission(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);

		$data = new Registry();
		$data->set('permissions', ['panopticon.super']);

		$userIds = (new RecipientResolver())->resolveUserIds($data, null);

		$this->assertContains((int) $user->getId(), $userIds);
	}

	public function testOnlyMailGroupsRestrictsToGivenGroups(): void
	{
		$groupId = random_int(100000, 999999);

		$user = $this->createUser(['parameters' => ['usergroups' => [$groupId]]]);

		$data = new Registry();
		// only_email_groups=true means permissions are ignored entirely; only email_groups count.
		$data->set('permissions', ['panopticon.nonexistent']);
		$data->set('email_groups', [$groupId]);
		$data->set('only_email_groups', true);

		$userIds = (new RecipientResolver())->resolveUserIds($data, null);

		$this->assertContains((int) $user->getId(), $userIds);
	}

	public function testNoMatchYieldsEmptyArray(): void
	{
		$data = new Registry();
		$data->set('permissions', ['panopticon.nonexistent']);

		$userIds = (new RecipientResolver())->resolveUserIds($data, null);

		$this->assertSame([], $userIds);
	}
}
