<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Selfupdate;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Selfupdate\Download;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/selfupdate/download.
 *
 * The happy path is NOT exercised: it performs an outbound HTTP fetch and writes to the
 * local filesystem. Only the auth boundary is asserted.
 *
 * @since  1.4.0
 */
class DownloadTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Selfupdate\\Download');

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testForbiddenForNonSuper(): void
	{
		$user = $this->createUser();
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(Download::class);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}
}
