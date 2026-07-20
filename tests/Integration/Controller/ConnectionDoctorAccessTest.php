<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Sites;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use ReflectionObject;
use RuntimeException;

/**
 * Tests the configurable secondary access control on the Connection Doctor.
 *
 * The Connection Doctor renders the raw response body and headers of whatever URL it is pointed at. The Forbidden IP
 * Ranges list bounds where it can be pointed, but only as far as the operator enumerated their network correctly, and
 * a hand-maintained list is eventually incomplete. This control is what limits the damage when the list is wrong.
 *
 * The tests exercise the access decision directly rather than dispatching the whole controller task, because the task
 * itself performs a live connection test.
 *
 * @since 1.4.0
 */
class ConnectionDoctorAccessTest extends AbstractIntegrationTestCase
{
	private mixed $originalMode = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalMode = $this->container->appConfig->get('connection_doctor_access', 'own');
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('connection_doctor_access', $this->originalMode);

		parent::tearDown();
	}

	private function setMode(string $mode): void
	{
		$this->container->appConfig->set('connection_doctor_access', $mode);
	}

	private function loginWithPrivileges(array $privileges): void
	{
		$parameters = [];

		foreach ($privileges as $privilege)
		{
			$parameters['acl.panopticon.' . $privilege] = 1;
		}

		$user    = $this->createUser(['parameters' => $parameters]);
		$manager = $this->container->userManager;

		$reflection = new ReflectionObject($manager);
		$property   = $reflection->getProperty('currentUser');
		$property->setValue($manager, $manager->getUser($user->getId()));
	}

	/**
	 * Invoke the controller's private access check and report whether it allowed the request.
	 */
	private function isAllowed(): bool
	{
		$controller = new Sites($this->container);

		$method = (new ReflectionObject($controller))->getMethod('assertConnectionDoctorAccess');

		try
		{
			$method->invoke($controller);

			return true;
		}
		catch (RuntimeException)
		{
			return false;
		}
	}

	/**
	 * The default preserves the behaviour Panopticon has always had: anyone who got past the caller's
	 * canAddEditOrSave() check may run the Doctor. Nobody is broken by upgrading.
	 */
	public function testDefaultModeAllowsSelfServiceUser(): void
	{
		$this->setMode('own');
		$this->loginWithPrivileges(['addown', 'editown']);

		$this->assertTrue($this->isAllowed());
	}

	public function testDefaultModeAllowsSuperUser(): void
	{
		$this->setMode('own');
		$this->loginWithPrivileges(['super']);

		$this->assertTrue($this->isAllowed());
	}

	/**
	 * The mode the advisory's hosted, multi-tenant scenario calls for: the paying customer with addown/editown is
	 * exactly the user who must not be able to read arbitrary internal responses.
	 */
	public function testAdminModeBlocksSelfServiceUser(): void
	{
		$this->setMode('admin');
		$this->loginWithPrivileges(['addown', 'editown']);

		$this->assertFalse($this->isAllowed());
	}

	public function testAdminModeAllowsAdmin(): void
	{
		$this->setMode('admin');
		$this->loginWithPrivileges(['admin']);

		$this->assertTrue($this->isAllowed());
	}

	/**
	 * The Super User privilege implies every other privilege, so a Super User must pass the admin-mode check even
	 * without an explicit admin grant.
	 */
	public function testAdminModeAllowsSuperUser(): void
	{
		$this->setMode('admin');
		$this->loginWithPrivileges(['super']);

		$this->assertTrue($this->isAllowed());
	}

	public function testSuperModeBlocksSelfServiceUser(): void
	{
		$this->setMode('super');
		$this->loginWithPrivileges(['addown', 'editown']);

		$this->assertFalse($this->isAllowed());
	}

	public function testSuperModeBlocksAdmin(): void
	{
		$this->setMode('super');
		$this->loginWithPrivileges(['admin']);

		$this->assertFalse($this->isAllowed());
	}

	public function testSuperModeAllowsSuperUser(): void
	{
		$this->setMode('super');
		$this->loginWithPrivileges(['super']);

		$this->assertTrue($this->isAllowed());
	}

	/**
	 * Prove the check is actually wired into the controller task, not merely present as a method.
	 *
	 * The site is owned by the acting user, so the pre-existing canAddEditOrSave() gate passes and the refusal can
	 * only be coming from the new control — which the asserted message confirms. The task throws before it reaches
	 * testConnection(), so no network access happens here.
	 */
	public function testControllerTaskEnforcesTheCheck(): void
	{
		$this->setMode('own');
		$this->loginWithPrivileges(['addown', 'editown']);

		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save([
			'name'    => 'Doctor ACL site',
			'url'     => 'https://doctor-acl.test/api',
			'enabled' => 1,
			'config'  => json_encode(['cmsType' => 'joomla']),
		]);

		// Now tighten the policy. The same user may still edit the site, but may no longer diagnose it.
		$this->setMode('super');

		// The task reads the id from the GET sub-input specifically, not from the merged input.
		$this->container->input->get->set('id', (int) $site->getId());
		$this->container->input->set('id', (int) $site->getId());

		// The task is CSRF protected. Supply the token the same way the real URL does, as `&<token>=1`.
		$this->container->input->set(
			$this->container->session->getCsrfToken()->getValue(),
			1
		);

		$controller = new Sites($this->container);

		try
		{
			$controller->connectionDoctor();

			$this->fail('Expected the Connection Doctor task to refuse a self-service user in super-only mode');
		}
		catch (RuntimeException $e)
		{
			$this->assertSame(403, $e->getCode());
			$this->assertSame(
				$this->container->language->text('PANOPTICON_SITES_ERR_CONNECTION_DOCTOR_FORBIDDEN'),
				$e->getMessage(),
				'The refusal must come from the Connection Doctor access control, not the ownership check'
			);
		}
	}

	/**
	 * An unrecognised value must not silently fail open into an unrestricted state. The configuration validator
	 * coerces anything unexpected back to the default, so this asserts the two layers agree.
	 */
	public function testUnknownModeFallsBackToTheDefault(): void
	{
		$this->setMode('this-is-not-a-valid-mode');

		$this->assertSame('own', $this->container->appConfig->get('connection_doctor_access'));

		$this->loginWithPrivileges(['addown', 'editown']);

		$this->assertTrue($this->isAllowed());
	}
}
