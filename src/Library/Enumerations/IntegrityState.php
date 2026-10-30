<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;

defined('AKEEBA') || die;

/**
 * Core file integrity (core checksums) state of a site.
 *
 * @since  2.5.0
 */
enum IntegrityState: string
{
	/** The core file integrity check has never run, nor failed. */
	case NeverRun = 'never_run';

	/** The last successful check found no modified core files. */
	case Ok = 'ok';

	/** The last successful check found modified core files. */
	case Problems = 'problems';

	/** The check could not be performed, and there is no problem report from a previous check. */
	case Failed = 'failed';
}
