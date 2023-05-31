<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

use Akeeba\Panopticon\Exception\AccessDenied;
use Awf\Utils\ArrayHelper;

defined('AKEEBA') || die;

trait ACLTrait
{
	/**
	 * Per-view and per-task privileges.
	 *
	 * The possible privileges are:
	 * - #      : Public access (even when logged out)
	 * - *      : Any logged-in access, even without any other explicit privileges
	 * - super  : Superusers
	 * - admin  : Administrator access
	 * - view   : View access
	 * - run    : Execute access
	 */
	protected array $aclChecks = [
		'captive'       => [
			'*' => ['*'],
		],
		'cron'          => [
			'*' => ['#'],
		],
		'emails'        => [
			'*' => ['super'],
		],
		'groups'        => [
			'*' => ['super'],
		],
		'login'         => [
			'*' => ['#'],
		],
		'mailtemplates' => [
			'*' => ['super'],
		],
		'main'          => [
			'*' => ['*'],
		],
		'mfamethod'     => [
			'*' => ['*'],
		],
		'mfamethods'    => [
			'*' => ['*'],
		],
		'setup'         => [
			'cron' => ['super'],
			'*'    => ['*'],
		],
		'sites'         => [
			'browse'                             => ['*'],
			'read'                               => ['read'],
			'fixJoomlaCoreUpdateSite'            => ['run'],
			'refreshSiteInformation'             => ['read'],
			'refreshExtensionsInformation'       => ['read'],
			'scheduleJoomlaUpdate'               => ['run'],
			'clearUpdateScheduleError'           => ['run'],
			'clearExtensionUpdatesScheduleError' => ['run'],
			'scheduleExtensionUpdate'            => ['run'],
			'*'                                  => ['admin'],
		],
		'selfupdate'    => [
			'*' => ['super'],
		],
		'sysconfig'     => [
			'*' => ['super'],
		],
		'tasks'         => [
			'*' => ['super'],
		],
		'users'         => [
			'*'     => ['super'],
			// User read (profile view) and edit has its own privilege management as users can edit their own account
			'edit'  => ['*'],
			'read'  => ['*'],
			'save'  => ['*'],
			'apply' => ['*'],
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

		// Special case: public access. Requires the '#' privilege.
		if (!$user->getId())
		{
			return array_reduce(
				$requiredPrivileges,
				fn($carry, $privilege) => $carry && $privilege === '#',
				true
			);
		}

		$id = $this->input->getInt('id', $this->input->get('cid', []));
		$id = is_array($id) ? ArrayHelper::toInteger($id) : [(int) $id];
		$id = (empty($id) ? 0 : array_pop($id)) ?: 0;

		// Per-site privileges for the Site view
		if (in_array(strtolower($view), ['sites', 'site']) && !empty($id))
		{
			return array_reduce(
				$requiredPrivileges,
				fn($carry,
				   $privilege) => $carry && (($privilege === '*') || ($privilege === '#') || $user->authorise('panopticon.' . $privilege, $id)),
				true
			);
		}

		// Global privileges for everything else
		return array_reduce(
			$requiredPrivileges,
			fn($carry,
			   $privilege) => $carry && (($privilege === '*') || $user->getPrivilege('panopticon.' . $privilege)),
			true
		);
	}
}