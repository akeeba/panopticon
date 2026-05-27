<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;

defined('AKEEBA') || die;

/**
 * Health status for a site status area returned by GET /api/v1/site/:id/status.
 *
 * Used by the Site Status API endpoint to communicate the health of each monitored area
 * (CMS updates, PHP, backup, file scanner, etc.) in a stable, machine-readable form.
 *
 * @since  1.6.2
 */
enum HealthStatus: string
{
	/** All checks passed; the area is healthy. */
	case Ok = 'ok';

	/** A potential issue exists but is not critical. */
	case Warning = 'warning';

	/** A critical issue exists that requires attention. */
	case Error = 'error';

	/** Insufficient data to determine health (e.g. monitoring not configured). */
	case Unknown = 'unknown';
}
