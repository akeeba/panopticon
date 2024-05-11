<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;


defined('AKEEBA') || die;

/**
 * Class WordPressUpdateRunState
 *
 * Represents the state of the WordPress update task for a site.
 *
 * @since  1.2.0
 */
enum WordPressUpdateRunState: int
{

	/**
	 * Represents a flag to indicate that a site is not a WordPress site, therefore it cannot have a WordPress update
	 * status.
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case NOT_A_WORDPRESS_SITE = -9999999;

	/**
	 * Represents a flag to indicate that the update state is invalid (we have no idea what is going on).
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case INVALID_STATE = -10000;

	/**
	 * CANNOT_UPGRADE constant represents a case where WordPress cannot perform an upgrade.
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case CANNOT_UPGRADE = -1000;

	/**
	 * NOT_SCHEDULED constant represents a case where an update task is not scheduled.
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case NOT_SCHEDULED = 0;

	/**
	 * SCHEDULED constant represents a case where an update task has been scheduled (future run).
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case SCHEDULED = 100;

	/**
	 * RUNNING constant represents a case where the update task is scheduled and currently running.
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case RUNNING = 200;

	/**
	 * ERROR constant represents an error during the last update task.
	 *
	 * @var   int
	 * @since 1.2.0
	 */
	case ERROR = 900;

	public function isValidUpdateState(): bool
	{
		return !$this->isValidRefreshState()
		       && !in_array(
				$this,
				[
					self::NOT_A_WORDPRESS_SITE,
					self::INVALID_STATE,
					self::CANNOT_UPGRADE,
				]
			);
	}

	public function isValidRefreshState(): bool
	{
		// We do not support core refresh for WordPress.
		return false;
	}
}
