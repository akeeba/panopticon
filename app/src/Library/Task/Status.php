<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Task;


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
}