<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SiteInfo;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\IntegrityState;
use Awf\Registry\Registry;

/**
 * Computes the core file integrity state of a site from its stored configuration.
 *
 * @since  2.5.0
 */
final class CoreChecksumsState
{
	/**
	 * Determine the core file integrity state from a site's configuration.
	 *
	 * A problem report (modified files) always wins over a newer failure, so the user is never lulled into a
	 * false sense of security; in that case `latestAttemptFailed` is true.
	 *
	 * @param   Registry  $config   The site configuration
	 * @param   CMSType   $cmsType  The CMS type of the site
	 *
	 * @return  array{state: IntegrityState, lastCheck: ?int, modifiedCount: int, lastFailure: ?int,
	 *          latestAttemptFailed: bool, failureReason: ?string}|null  Null if not applicable to this CMS.
	 * @since   2.5.0
	 */
	public static function fromConfig(Registry $config, CMSType $cmsType): ?array
	{
		if (!in_array($cmsType, [CMSType::JOOMLA, CMSType::WORDPRESS], true))
		{
			return null;
		}

		$lastCheck     = $config->get('core.coreChecksums.lastCheck');
		$lastCheck     = empty($lastCheck) ? null : (int) $lastCheck;
		$lastStatus    = $config->get('core.coreChecksums.lastStatus');
		$modifiedCount = (int) $config->get('core.coreChecksums.modifiedCount', 0);
		$lastFailure   = $config->get('core.coreChecksums.lastFailure');
		$lastFailure   = empty($lastFailure) ? null : (int) $lastFailure;
		$reason        = $config->get('core.coreChecksums.lastFailureReason');
		$reason        = ($reason === null || $reason === '') ? null : (string) $reason;

		$latestAttemptFailed = $lastFailure !== null && ($lastCheck === null || $lastFailure > $lastCheck);

		if ($lastCheck === null && $lastFailure === null)
		{
			$state = IntegrityState::NeverRun;
		}
		elseif ($lastCheck !== null && $lastStatus === false)
		{
			$state = IntegrityState::Problems;
		}
		elseif ($latestAttemptFailed)
		{
			$state = IntegrityState::Failed;
		}
		else
		{
			$state = IntegrityState::Ok;
		}

		return [
			'state'               => $state,
			'lastCheck'           => $lastCheck,
			'modifiedCount'       => $modifiedCount,
			'lastFailure'         => $lastFailure,
			'latestAttemptFailed' => $latestAttemptFailed,
			'failureReason'       => $reason,
		];
	}
}
