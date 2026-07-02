<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Input\Input;
use Awf\Mvc\Controller;

/**
 * Base class for web-controller integration tests.
 *
 * Unlike the API tests, these exercise the ordinary (HTML) controllers directly: they set the
 * authenticated user on the userManager, install a request Input on the container, and call
 * Controller::execute($task) so the full onBefore/task/onAfter hook chain — including
 * csrfProtection() and the ACLTrait authority checks — runs exactly as it does for a browser
 * request. State-changing tasks use setRedirect() (which does not exit), so execute() returns
 * normally; authority/CSRF failures surface as thrown exceptions which the tests assert on.
 *
 * @since  2.2.0
 */
abstract class AbstractControllerIntegrationTestCase extends AbstractIntegrationTestCase
{
	/**
	 * Set the currently authenticated user on the container's userManager.
	 *
	 * AWF's UserManager keeps the current user in a protected property with no public setter for a
	 * synthetic login, so we set it via reflection (mirrors the API test harness).
	 *
	 * @param   int  $userId  The user id to log in as, or 0 for a guest.
	 */
	protected function loginAs(int $userId): void
	{
		$manager = $this->container->userManager;
		$user    = $manager->getUser($userId);

		$ref      = new \ReflectionObject($manager);
		$property = $ref->getProperty('currentUser');
		$property->setAccessible(true);
		$property->setValue($manager, $user);
	}

	/**
	 * The current session's anti-CSRF token value, as csrfProtection() expects to receive it.
	 *
	 * @return  string
	 */
	protected function csrfToken(): string
	{
		return $this->container->session->getCsrfToken()->getValue();
	}

	/**
	 * Dispatch a web controller task and return the controller instance after execution.
	 *
	 * A valid anti-CSRF token is injected automatically unless the caller already supplied a
	 * `token` key or sets $withCsrf to false (used to assert that a task rejects a missing token).
	 * Any exception thrown by the hook chain (AccessDenied, "Invalid security token", ownership
	 * 403, …) propagates to the caller so tests can assert on it.
	 *
	 * @param   string               $controllerClass  FQCN of the controller.
	 * @param   string               $task             The task to execute.
	 * @param   array<string,mixed>  $input            Request parameters (GET/POST merged).
	 * @param   bool                 $withCsrf         Inject a valid token when none is provided.
	 *
	 * @return  Controller
	 */
	protected function dispatch(string $controllerClass, string $task, array $input = [], bool $withCsrf = true): Controller
	{
		if ($withCsrf && !isset($input['token']))
		{
			$input['token'] = $this->csrfToken();
		}

		$originalInput = $this->container->input;
		$newInput      = new Input($input);

		// AWF's Controller copies its Input from $container->input in the constructor, so the
		// container input must be swapped BEFORE the controller is instantiated.
		unset($this->container['input']);
		$this->container['input'] = fn() => $newInput;

		try
		{
			/** @var Controller $controller */
			$controller = new $controllerClass($this->container);
			$controller->execute($task);

			return $controller;
		}
		finally
		{
			unset($this->container['input']);
			$this->container['input'] = fn() => $originalInput;
		}
	}

	/**
	 * Run a controller's ACLTrait::aclCheck() for a task in isolation (no task body / network).
	 *
	 * Installs an Input with the given request params, instantiates the controller, and invokes the
	 * protected aclCheck() via reflection. Throws {@see \Akeeba\Panopticon\Exception\AccessDenied}
	 * when access is denied, exactly as it would during a real dispatch.
	 *
	 * @param   string               $controllerClass  FQCN of the controller.
	 * @param   string               $task             The task whose ACL to evaluate.
	 * @param   array<string,mixed>  $input            Request parameters (e.g. ['id' => 5]).
	 */
	protected function runAclCheck(string $controllerClass, string $task, array $input = []): void
	{
		$originalInput = $this->container->input;
		$newInput      = new Input($input);

		unset($this->container['input']);
		$this->container['input'] = fn() => $newInput;

		try
		{
			/** @var Controller $controller */
			$controller = new $controllerClass($this->container);

			$ref    = new \ReflectionObject($controller);
			$method = $ref->getMethod('aclCheck');
			$method->setAccessible(true);
			$method->invoke($controller, $task);
		}
		finally
		{
			unset($this->container['input']);
			$this->container['input'] = fn() => $originalInput;
		}
	}

	/**
	 * Insert a site and return it.
	 *
	 * A site's `created_by` column has a foreign key to #__users, so a valid owner is created and
	 * assigned unless one is supplied. Overridable fields: name, url, enabled, config, created_by.
	 *
	 * @param   array<string,mixed>  $overrides
	 *
	 * @return  Site
	 */
	protected function createSite(array $overrides = []): Site
	{
		// A valid actor must be logged in while saving: Site::check() stamps created_by (and, when
		// created_by is pre-set, modified_by) from the current user, and both columns have a FK to
		// #__users. Saving as a guest (id 0) would violate that FK.
		if (isset($overrides['created_by']))
		{
			$actorId = (int) $overrides['created_by'];
		}
		else
		{
			$owner   = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);
			$actorId = (int) $owner->getId();
		}

		$suffix = bin2hex(random_bytes(3));
		$data   = array_merge(
			[
				'name'    => 'Test site ' . $suffix,
				'url'     => 'https://test-' . $suffix . '.example/',
				'enabled' => 1,
				'config'  => null,
			],
			$overrides
		);

		$manager  = $this->container->userManager;
		$ref      = new \ReflectionObject($manager);
		$property = $ref->getProperty('currentUser');
		$property->setAccessible(true);
		$previous = $manager->getUser();

		$property->setValue($manager, $manager->getUser($actorId));

		try
		{
			/** @var Site $site */
			$site = $this->container->mvcFactory->makeTempModel('Site');
			$site->save($data);
		}
		finally
		{
			$property->setValue($manager, $previous);
		}

		return $site;
	}

	/**
	 * Assert a row exists (count === expected) for the given id in a table.
	 *
	 * @param   string  $table     Table name (with #__ prefix placeholder).
	 * @param   int     $id        Primary key value.
	 * @param   int     $expected  Expected row count (0 or 1).
	 * @param   string  $idColumn  Primary key column name.
	 */
	protected function assertRowCount(string $table, int $id, int $expected, string $idColumn = 'id'): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn($table))
			->where($db->qn($idColumn) . ' = ' . (int) $id);

		$this->assertSame($expected, (int) $db->setQuery($query)->loadResult());
	}
}
