<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;


defined('AKEEBA') || die;

/**
 * Enumeration for the `run_once` task parameter
 */
enum RunOnce: string
{
	// This is not a run once task
	case NONE    = '';

	// Disable after execution
	case DISABLE = 'disable';

	// Delete after execution
	case DELETE  = 'delete';
}
