<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

use Akeeba\Panopticon\Exception\AccessDenied;

defined('AKEEBA') || die;

trait ACLTrait
{
	protected array $aclChecks = [
		'cron'      => [
			'*' => ['*'],
		],
		'emails'    => [
			'*' => ['super'],
		],
		'login'     => [
			'*' => ['*'],
		],
		'mailtemplates' => [
			'*' => ['super'],
		],
		'main'      => [
			'*' => ['view'],
		],
		'setup'     => [
			'cron' => ['super'],
			'*'    => ['*'],
		],
		'sites'     => [
			'*' => ['admin'],
		],
		'sysconfig' => [
			'*' => ['super'],
		],
		'tasks'     => [
			'*' => ['super'],
		],
	];

	protected function aclCheck(string $task): void
	{
		if ($this->hasAccess($task, $this->getName()))
		{
			return;
		}

		throw new AccessDenied();
	}

	protected function hasAccess(?string $task = null, ?string $view = null): bool
	{
		// Get and normalise the view and task
		$view ??= $this->input->getCmd('view', 'main');
		$task ??= $this->input->getCmd('task', 'default');

		if (str_contains($task, '.'))
		{
			[$view, $task] = explode('.', $task, 2);
		}

		$view = strtolower($view);
		$task = strtolower($task);

		// Determine the configured privileges
		$requiredPrivileges = $this->aclChecks[$view][$task]
			?? $this->aclChecks[$view]['*']
			?? $this->aclChecks['*'][$task]
			?? $this->aclChecks['*']['*']
			?? [];

		// No ACLs for the entire view, or the specific task (without a '*' task fallback)? Implicitly forbidden.
		if (empty($requiredPrivileges))
		{
			return false;
		}

		$user = $this->container->userManager->getUser();

		return array_reduce(
			$requiredPrivileges,
			fn($carry, $privilege) => $carry && (($privilege === '*') || $user->getPrivilege('panopticon.' . $privilege)),
			true
		);
	}
}