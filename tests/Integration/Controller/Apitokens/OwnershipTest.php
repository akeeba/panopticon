<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Apitokens;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Apitokens;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;

/**
 * Regression tests for GHSA-style IDOR on the API tokens controller: a logged-in user must not be
 * able to enable, disable, or delete another user's API tokens via publish/unpublish/remove
 * (ownership was previously enforced only on save/apply).
 *
 * @since  2.2.0
 */
class OwnershipTest extends AbstractControllerIntegrationTestCase
{
	public function testRemoveOtherUsersTokenIsForbidden(): void
	{
		$attacker = $this->createUser();
		$victim   = $this->createUser();
		['row' => $victimToken] = $this->createApiToken((int) $victim->getId());

		$this->loginAs((int) $attacker->getId());

		try
		{
			$this->dispatch(Apitokens::class, 'remove', ['cid' => [(int) $victimToken->getId()]]);
			$this->fail('Expected a 403 when deleting another user\'s API token.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(403, $e->getCode());
		}

		// The victim's token must still exist.
		$this->assertRowCount('#__api_tokens', (int) $victimToken->getId(), 1);
	}

	public function testUnpublishOtherUsersTokenIsForbidden(): void
	{
		$attacker = $this->createUser();
		$victim   = $this->createUser();
		['row' => $victimToken] = $this->createApiToken((int) $victim->getId(), ['enabled' => 1]);

		$this->loginAs((int) $attacker->getId());

		try
		{
			$this->dispatch(Apitokens::class, 'unpublish', ['cid' => [(int) $victimToken->getId()]]);
			$this->fail('Expected a 403 when disabling another user\'s API token.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(403, $e->getCode());
		}

		// The victim's token must still be enabled.
		$reloaded = (new \Akeeba\Panopticon\Model\Apitoken($this->container))->findOrFail((int) $victimToken->getId());
		$this->assertSame(1, (int) $reloaded->enabled);
	}

	public function testUserCanRemoveOwnToken(): void
	{
		$user = $this->createUser();
		['row' => $ownToken] = $this->createApiToken((int) $user->getId());

		$this->loginAs((int) $user->getId());

		$this->dispatch(Apitokens::class, 'remove', ['cid' => [(int) $ownToken->getId()]]);

		// The user's own token must be gone.
		$this->assertRowCount('#__api_tokens', (int) $ownToken->getId(), 0);
	}

	public function testSuperUserCanRemoveAnyToken(): void
	{
		$super  = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$victim = $this->createUser();
		['row' => $victimToken] = $this->createApiToken((int) $victim->getId());

		$this->loginAs((int) $super->getId());

		$this->dispatch(Apitokens::class, 'remove', ['cid' => [(int) $victimToken->getId()]]);

		$this->assertRowCount('#__api_tokens', (int) $victimToken->getId(), 0);
	}
}
