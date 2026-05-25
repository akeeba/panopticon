<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\ExtensionDownloadKeySet;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for POST /v1/site/:id/extension/:extId/downloadkey.
 *
 * Happy path is **not** asserted because saving a download key calls the remote connector via
 * Guzzle. Only auth, validation, and not-found branches are exercised.
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeySetTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$this->setJsonRequestBody(['key' => 'ABC-123']);

		$response = $this->dispatchApi('V1\\Site\\ExtensionDownloadKeySet', ['id' => 1, 'extId' => 42]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
		$this->setJsonRequestBody(['key' => 'ABC-123']);

		$response = $this->invokeHandler(ExtensionDownloadKeySet::class, ['id' => 999999999, 'extId' => 42]);

		$this->assertSame(404, $response['status']);
		$this->assertSame('site.not_found', $response['body']['code']);
	}

	public function testForbiddenWithoutAdmin(): void
	{
		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $owner->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeySet ACL site',
			'url'     => 'https://dkset-acl.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());
		$this->setJsonRequestBody(['key' => 'ABC-123']);

		$response = $this->invokeHandler(
			ExtensionDownloadKeySet::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(403, $response['status']);
		$this->assertSame('auth.forbidden', $response['body']['code']);
	}

	public function testWrongCmsReturns422(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeySet wrong CMS',
			'url'     => 'https://dkset-wrong.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'wordpress']),
		]);

		$this->setJsonRequestBody(['key' => 'ABC-123']);

		$response = $this->invokeHandler(
			ExtensionDownloadKeySet::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(422, $response['status']);
		$this->assertSame('site.wrong_cms', $response['body']['code']);
	}

	public function testMissingKeyReturns400(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeySet missing key',
			'url'     => 'https://dkset-misskey.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		// Empty body — no "key" field.
		$this->setJsonRequestBody([]);

		$response = $this->invokeHandler(
			ExtensionDownloadKeySet::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(400, $response['status']);
		$this->assertSame('validation.bad_request', $response['body']['code']);
	}

	public function testExtensionNotFoundReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeySet no-ext',
			'url'     => 'https://dkset-noext.test/api',
			'enabled' => 1,
			'config'  => json_encode([
				'cmsType'    => 'joomla',
				'extensions' => ['list' => []],
			]),
		]);

		$this->setJsonRequestBody(['key' => 'ABC-123']);

		$response = $this->invokeHandler(
			ExtensionDownloadKeySet::class,
			['id' => (int) $site->getId(), 'extId' => 9999]
		);

		$this->assertSame(404, $response['status']);
		$this->assertSame('extension.not_found', $response['body']['code']);
	}
}
