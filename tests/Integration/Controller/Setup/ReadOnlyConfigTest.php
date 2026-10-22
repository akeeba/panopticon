<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Setup;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application;
use Akeeba\Panopticon\Controller\Setup;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;
use Awf\Application\ApplicationServiceProvider;

/**
 * Setup must be able to finish when config.php cannot be written to.
 *
 * On a shared host the user may upload config.php by hand, and it then stays read-only to PHP. Finishing the setup
 * used to throw while saving the configuration. It must instead show the final configuration, to be uploaded again.
 *
 * The test environment loads its configuration from .env.test, so saveConfiguration() throws ReadOnlyRepository.
 * That is the read-only condition, with no mocking needed.
 *
 * @since 2.4.0
 */
class ReadOnlyConfigTest extends AbstractControllerIntegrationTestCase
{
	private mixed $originalFinishedSetup = null;

	private mixed $originalInstalling = null;

	protected function setUp(): void
	{
		parent::setUp();

		// The API tests leave a stub application in the shared container. Setup needs the real one, for its document.
		if (!($this->container->application instanceof Application))
		{
			unset($this->container['application']);
			$this->container['application'] = new ApplicationServiceProvider();
		}

		$this->originalFinishedSetup = $this->container->appConfig->get('finished_setup', false);
		$this->originalInstalling    = $this->container->segment->get('panopticon.installing', false);

		$this->container->appConfig->set('finished_setup', false);
		$this->container->segment->set('panopticon.installing', true);

		$user = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
		$this->loginAs((int) $user->getId());
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('finished_setup', $this->originalFinishedSetup);
		$this->container->segment->set('panopticon.installing', $this->originalInstalling);

		parent::tearDown();
	}

	public function testSkipcronWithReadOnlyConfigShowsFinalConfiguration(): void
	{
		ob_start();

		try
		{
			$controller = $this->dispatch(Setup::class, 'skipcron', ['task' => 'skipcron']);
		}
		finally
		{
			$output = ob_get_clean();
		}

		$this->assertSame('saveconfig', $controller->getView()->getLayout());
		$this->assertStringContainsString('finished_setup', $output);
		$this->assertStringContainsString('view=main', $output);
		$this->assertStringNotContainsString('task=cron', $output);
	}

	public function testFinishWithReadOnlyConfigShowsFinalConfiguration(): void
	{
		ob_start();

		try
		{
			$controller = $this->dispatch(Setup::class, 'finish', ['task' => 'finish', 'maxexec' => 60]);
		}
		finally
		{
			$output = ob_get_clean();
		}

		$this->assertSame('saveconfig', $controller->getView()->getLayout());
		$this->assertStringContainsString('finished_setup', $output);
		$this->assertStringContainsString('max_execution', $output);
		$this->assertStringContainsString('view=main', $output);
		$this->assertStringNotContainsString('task=cron', $output);
	}
}
