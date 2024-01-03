<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;


defined('AKEEBA') || die;

/**
 * Class JoomlaUpdateRunState
 *
 * Represents the state of the Joomla! Update task for a site.
 *
 * @since  1.0.6
 */
enum JoomlaUpdateRunState: int
{

	/**
	 * Represents a flag to indicate that a site is not a Joomla! site, therefore it cannot have a Joomla! update
	 * status.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case NOT_A_JOOMLA_SITE = -9999999;

	/**
	 * Represents a flag to indicate that the update state is invalid (we have no idea what is going on).
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case INVALID_STATE = -10000;

	/**
	 * CANNOT_UPGRADE constant represents a case where Joomla! Update cannot perform an upgrade.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case CANNOT_UPGRADE = -1000;

	/**
	 * NOT_SCHEDULED constant represents a case where an update task is not scheduled.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case NOT_SCHEDULED = 0;

	/**
	 * SCHEDULED constant represents a case where an update task has been scheduled (future run).
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case SCHEDULED = 100;

	/**
	 * RUNNING constant represents a case where the update task is scheduled and currently running.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case RUNNING = 200;

	/**
	 * ERROR constant represents an error during the last update task.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case ERROR = 900;

	/**
	 * REFRESH_SCHEDULED constant represents a core refresh that has been scheduled (future run).
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case REFRESH_SCHEDULED = 1100;

	/**
	 * REFRESH_SCHEDULED constant represents a core refresh that has been scheduled and currently running.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case REFRESH_RUNNING = 1200;

	/**
	 * REFRESH_ERROR constant represents an error during the last core refresh.
	 *
	 * @var   int
	 * @since 1.0.6
	 */
	case REFRESH_ERROR = 1900;

	public function isValidUpdateState(): bool
	{
		return !$this->isValidRefreshState()
		       && !in_array(
				$this,
				[
					self::NOT_A_JOOMLA_SITE,
					self::INVALID_STATE,
					self::CANNOT_UPGRADE,
				]
			);
	}

	public function isValidRefreshState(): bool
	{
		return in_array(
			$this,
			[
				self::REFRESH_SCHEDULED,
				self::REFRESH_RUNNING,
				self::REFRESH_ERROR,
			]
		);
	}
}
