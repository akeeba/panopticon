<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\UserAdd;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\User\Manager;
use Awf\User\UserInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * user:add must report a failure to save the user as a clean error, not an uncaught exception.
 *
 * The user manager is replaced with one whose saveUser() throws, the way AWF's Manager::saveUser() does when the
 * database rejects the query. Nothing is written to the database.
 *
 * @since 2.4.0
 */
class UserAddTest extends AbstractIntegrationTestCase
{
	private mixed $originalUserManager = null;

	protected function setUp(): void
	{
		parent::setUp();

		// Skip the live haveibeenpwned.com call; it is irrelevant to what is being tested.
		$this->container->appConfig->set('password_hibp', 0);

		$this->originalUserManager = $this->container->userManager;
		unset($this->container['userManager']);
		$this->container['userManager'] = fn($c) => new class($c) extends Manager {
			public function saveUser(UserInterface $user)
			{
				throw new \RuntimeException('Simulated database failure');
			}
		};
	}

	protected function tearDown(): void
	{
		if ($this->originalUserManager !== null)
		{
			unset($this->container['userManager']);
			$this->container['userManager'] = $this->originalUserManager;
			$this->originalUserManager      = null;
		}

		parent::tearDown();
	}

	public function testSaveFailureIsReportedAsAnError(): void
	{
		$application = new Application('Akeeba Panopticon CLI', 'test');
		$application->setAutoExit(false);
		$application->add(new UserAdd());

		$username = 'testuser_' . bin2hex(random_bytes(4));
		$tester   = new CommandTester($application->find('user:add'));

		$exitCode = $tester->execute(
			[
				'--username' => $username,
				'--password' => 'Tst_' . bin2hex(random_bytes(12)),
				'--email'    => $username . '@example.invalid',
			],
			['interactive' => false]
		);

		$display = $tester->getDisplay();

		$this->assertSame(Command::FAILURE, $exitCode);
		$this->assertStringContainsString(sprintf('Could not save user %s.', $username), $display);
		$this->assertStringContainsString('Simulated database failure', $display);
		$this->assertStringNotContainsString('Successfully saved user', $display);
	}
}
