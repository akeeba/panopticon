<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;


use Akeeba\Panopticon\Factory;
use Awf\Text\Text;

defined('AKEEBA') || die;

enum Status: int
{
	// Exit code was not an integer
	case INVALID_EXIT = -2;

	// No exit code returned (returned NULL, or is a void method)
	case NO_EXIT = -1;

	// Successful execution
	case OK = 0;

	// Executing, no exit recorded yet
	case RUNNING = 1;

	// Failed to acquire lock
	case NO_LOCK = 2;

	// Failed to start the execution.
	case NO_RUN = 3;

	// Failed to release the lock, or update the DB record
	case NO_RELEASE = 4;

	// Task run resulted in an unhandled exception
	case EXCEPTION = 5;

	// The task has never run
	case INITIAL_SCHEDULE = 100;

	// The task has not finished running (reschedule it a.s.a.p)
	case WILL_RESUME = 123;

	// PHP timeout
	case TIMEOUT = 124;

	// The task ID we tried to run (or update) does not exist.
	case NO_TASK = 125;

	// The task type is unknown
	case NO_ROUTINE = 127;

	public function forHumans()
	{
		$lang = Factory::getContainer()->language;
		
		return match ($this)
		{
			self::INVALID_EXIT => $lang->text('PANOPTICON_APP_LBL_STATUS_INVALID_EXIT'),
			self::NO_EXIT => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_EXIT'),
			self::OK => $lang->text('PANOPTICON_APP_LBL_STATUS_OK'),
			self::RUNNING => $lang->text('PANOPTICON_APP_LBL_STATUS_RUNNING'),
			self::NO_LOCK => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_LOCK'),
			self::NO_RUN => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_RUN'),
			self::NO_RELEASE => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_RELEASE'),
			self::EXCEPTION => $lang->text('PANOPTICON_APP_LBL_STATUS_EXCEPTION'),
			self::INITIAL_SCHEDULE => $lang->text('PANOPTICON_APP_LBL_STATUS_INITIAL_SCHEDULE'),
			self::WILL_RESUME => $lang->text('PANOPTICON_APP_LBL_STATUS_WILL_RESUME'),
			self::TIMEOUT => $lang->text('PANOPTICON_APP_LBL_STATUS_TIMEOUT'),
			self::NO_TASK => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_TASK'),
			self::NO_ROUTINE => $lang->text('PANOPTICON_APP_LBL_STATUS_NO_ROUTINE'),
		};
	}
}