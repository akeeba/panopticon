<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Api\V1\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\V1\Site\ExtensionDownloadKeyGet;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\Integration\Api\AbstractApiIntegrationTestCase;

/**
 * Integration tests for GET /v1/site/:id/extension/:extId/downloadkey.
 *
 * @since  1.4.0
 */
class ExtensionDownloadKeyGetTest extends AbstractApiIntegrationTestCase
{
	public function testUnauthorisedWithNoToken(): void
	{
		$response = $this->dispatchApi('V1\\Site\\ExtensionDownloadKeyGet', ['id' => 1, 'extId' => 42]);

		$this->assertSame(401, $response['status']);
		$this->assertSame('auth.invalid_token', $response['body']['code']);
	}

	public function testNotFoundForUnknownSite(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		$response = $this->invokeHandler(ExtensionDownloadKeyGet::class, ['id' => 999999999, 'extId' => 42]);

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
			'name'    => 'DlKeyGet ACL site',
			'url'     => 'https://dkget-acl.test/api',
			'enabled' => 1,
			'config'  => json_encode([
				'cmsType'    => 'joomla',
				'extensions' => ['list' => [42 => ['id' => 42, 'name' => 'com_example']]],
			]),
		]);

		$intruder = $this->createUser();
		$this->loginAs((int) $intruder->getId());

		$response = $this->invokeHandler(
			ExtensionDownloadKeyGet::class,
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
			'name'    => 'DlKeyGet wrong CMS',
			'url'     => 'https://dkget-wrong.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'wordpress']),
		]);

		$response = $this->invokeHandler(
			ExtensionDownloadKeyGet::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(422, $response['status']);
		$this->assertSame('site.wrong_cms', $response['body']['code']);
	}

	public function testExtensionNotFoundReturns404(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeyGet no-ext',
			'url'     => 'https://dkget-noext.test/api',
			'enabled' => 1,
			'config'  => json_encode([
				'cmsType'    => 'joomla',
				'extensions' => ['list' => []],
			]),
		]);

		$response = $this->invokeHandler(
			ExtensionDownloadKeyGet::class,
			['id' => (int) $site->getId(), 'extId' => 9999]
		);

		$this->assertSame(404, $response['status']);
		$this->assertSame('extension.not_found', $response['body']['code']);
	}

	public function testSuccessReturnsDownloadKeyInfo(): void
	{
		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'DlKeyGet ok',
			'url'     => 'https://dkget-ok.test/api',
			'enabled' => 1,
			'config'  => json_encode([
				'cmsType'    => 'joomla',
				'extensions' => [
					'list' => [
						42 => [
							'id'          => 42,
							'name'        => 'com_example',
							'description' => 'Example',
							'downloadkey' => [
								'supported'   => true,
								'valid'       => true,
								'prefix'      => '',
								'suffix'      => '',
								'updatesites' => [7],
								'value'       => 'ABC-123',
							],
						],
					],
				],
			]),
		]);

		$response = $this->invokeHandler(
			ExtensionDownloadKeyGet::class,
			['id' => (int) $site->getId(), 'extId' => 42]
		);

		$this->assertSame(200, $response['status']);
		$this->assertTrue($response['body']['success']);
		$this->assertSame(42, $response['body']['data']['extensionId']);
		$this->assertTrue($response['body']['data']['downloadkey']['supported']);
		$this->assertSame('ABC-123', $response['body']['data']['downloadkey']['value']);
	}
}
