<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Apitoken;
use Awf\User\User;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests.
 *
 * Wraps every test in a database transaction that is rolled back during tearDown. This keeps
 * per-test state isolated while leaving the (idempotent) DDL applied at bootstrap intact —
 * DDL implicitly commits in MySQL and would defeat the rollback, so integration tests must
 * stick to DML.
 *
 * @since 1.4.0
 */
abstract class AbstractIntegrationTestCase extends TestCase
{
	protected Container $container;

	protected function setUp(): void
	{
		parent::setUp();

		$this->container = Factory::getContainer();
		$this->container->db->connect();
		$this->container->db->transactionStart();
	}

	protected function tearDown(): void
	{
		try
		{
			$this->container->db->transactionRollback();
		}
		catch (\Throwable)
		{
			// If the transaction was already ended, ignore — the next test starts a fresh one.
		}

		parent::tearDown();
	}

	/**
	 * Insert a user into #__users and return the User object (with id populated).
	 *
	 * Overridable fields: username, name, email, password (plain), parameters (JSON or array).
	 *
	 * @param   array<string, mixed>  $overrides
	 *
	 * @return  User
	 */
	protected function createUser(array $overrides = []): User
	{
		$suffix = bin2hex(random_bytes(4));

		$defaults = [
			'username'   => 'testuser_' . $suffix,
			'name'       => 'Test User ' . $suffix,
			'email'      => 'testuser_' . $suffix . '@example.test',
			'password'   => 'P@ssw0rd!' . $suffix,
			'parameters' => [],
		];

		$data = array_merge($defaults, $overrides);

		$userManager = $this->container->userManager;
		$user        = $userManager->getUser(0);
		$user->setUsername((string) $data['username']);
		$user->setName((string) $data['name']);
		$user->setEmail((string) $data['email']);
		$user->setPassword((string) $data['password']);

		if (!empty($data['parameters']) && is_array($data['parameters']))
		{
			$parameters = $user->getParameters();

			foreach ($data['parameters'] as $key => $value)
			{
				$parameters->set($key, $value);
			}
		}

		$userManager->saveUser($user);

		return $user;
	}

	/**
	 * Create an enabled API token row for $userId and return the plain-text token string plus
	 * the persisted Apitoken row (with id populated).
	 *
	 * Overridable fields: enabled, expires_at, scopes (JSON), name.
	 *
	 * @param   int                   $userId
	 * @param   array<string, mixed>  $overrides
	 *
	 * @return  array{token: string, row: Apitoken}
	 */
	protected function createApiToken(int $userId, array $overrides = []): array
	{
		$seed       = Apitoken::generateSeed();
		$siteSecret = (string) $this->container->appConfig->get('secret', '');
		$token      = Apitoken::computeToken($seed, $userId, $siteSecret);

		$row = new Apitoken($this->container);

		$data = array_merge(
			[
				'user_id'    => $userId,
				'enabled'    => 1,
				'seed'       => $seed,
				'expires_at' => null,
				'scopes'     => null,
				'name'       => 'Test token',
			],
			$overrides
		);

		$row->save($data);

		return ['token' => $token, 'row' => $row];
	}
}
