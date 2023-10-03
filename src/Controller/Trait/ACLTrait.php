<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

use Akeeba\Panopticon\Exception\AccessDenied;
use Awf\Inflector\Inflector;
use Awf\Utils\ArrayHelper;

defined('AKEEBA') || die;

trait ACLTrait
{
	/**
	 * Per-view and per-task privileges.
	 *
	 * The possible privileges are:
	 * - ø      : Forbidden (even to superusers)
	 * - #      : Public access (even when logged out)
	 * - *      : Any logged-in access, even without any other explicit privileges
	 * - super  : Superusers
	 * - admin  : Administrator access
	 * - view   : View access
	 * - run    : Execute access
	 */
	protected array $aclChecks = [
		'about'         => [
			'*' => ['*'],
		],
		'captive'       => [
			'*' => ['*'],
		],
		'backuptasks'   => [
			// We use per-site privileges in this controller
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
		'log'           => [
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
		'overrides'     => [
			'default' => ['read'],
			'browse'  => ['read'],
			'read'    => ['read'],
		],
		'setup'         => [
			'cron' => ['super'],
			'*'    => ['*'],
		],
		'sites'         => [
			// Anyone can browse; their view will be limited to the sites they have a view privilege on.
			// IMPORTANT: `default` is necessary, it's used when we do not pass a task to the view.
			'default'                            => ['*'],
			'browse'                             => ['*'],
			'read'                               => ['*'],
			// To add a new site you need to have the addown or admin privilege
			'add'                                => ['addown', 'admin'],
			// To edit the Download Key you need editown or admin (these are finely checked in the controller)
			'dlkey'                              => ['*'],
			'savedlkey'                          => ['*'],
			// To edit, apply, or save you need editown or admin (these are finely checked in the controller)
			'edit'                               => ['*'],
			'apply'                              => ['*'],
			'save'                               => ['*'],
			// Reloading a site's information requires the read privilege on it
			'refreshSiteInformation'             => ['read'],
			'refreshExtensionsInformation'       => ['read'],
			// Actions which modify the site need the run privilege
			'fixJoomlaCoreUpdateSite'            => ['run'],
			'scheduleJoomlaUpdate'               => ['run'],
			'clearUpdateScheduleError'           => ['run'],
			'clearExtensionUpdatesScheduleError' => ['run'],
			'scheduleExtensionUpdate'            => ['run'],
			// Anything else (like publish / unpublish), we automatically restrict to the admin privilege
			'*'                                  => ['admin'],
		],
		'selfupdate'    => [
			'*' => ['super'],
		],
		'sysconfig'     => [
			'default'   => ['super'],
			'browse'    => ['super'],
			'save'      => ['super'],
			'apply'     => ['super'],
			'cancel'    => ['super'],
			'testemail' => ['super'],
		],
		'tasks'         => [
			// Explicitly allowed tasks. Not adding other tasks means they are implicitly disallowed, even to superusers.
			'default'   => ['super'],
			'browse'    => ['super'],
			'publish'   => ['super'],
			'unpublish' => ['super'],
			'remove'    => ['super'],
		],
		'users'         => [
			// Explicitly allowed tasks. Using * because they have their own access control (I can view / edit myself).
			// Not adding other tasks means they are implicitly disallowed, even to superusers.
			'browse'  => ['super'],
			'default' => ['super'],
			'add'     => ['super'],
			'edit'    => ['*'],
			'read'    => ['*'],
			'save'    => ['*'],
			'apply'   => ['*'],
			'cancel'  => ['*'],
		],
	];

	protected function aclCheck(string $task): void
	{
		$altView = Inflector::isSingular($this->getName()) ? Inflector::pluralize($this->getName())
			: Inflector::singularize($this->getName());

		if ($this->hasAccess($task, $this->getName()) || $this->hasAccess($task, $altView))
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

		// Special case: explicitly forbidden. Uses the 'ø' privilege.
		$isExplicitlyForbidden = array_reduce(
			$requiredPrivileges,
			fn($carry, $privilege) => $carry || $privilege === 'ø',
			false
		);

		if (!$isExplicitlyForbidden)
		{
			return true;
		}

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
				fn(
					$carry,
					$privilege
				) => $carry
				     && (($privilege === '*') || ($privilege === '#')
				         || $user->authorise(
							'panopticon.' . $privilege, $id
						)),
				true
			);
		}

		// Global privileges for everything else
		return array_reduce(
			$requiredPrivileges,
			fn(
				$carry,
				$privilege
			) => $carry && (($privilege === '*') || $user->getPrivilege('panopticon.' . $privilege)),
			true
		);
	}
}