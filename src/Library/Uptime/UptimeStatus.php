<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Uptime;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\DataShape\AbstractDataShape;

/**
 * Represents the uptime status of a site
 *
 * @property  bool        $up          Is the site up?
 * @property  null|int    $downSince   Since when is the site down?
 * @property  bool        $isScheduled Is the site expected to be down, e.g. scheduled maintenance? (3PD)
 * @property  null|string $detailsUrl  A URL with more uptime details. (3PD)
 *
 * Fields marked as `(3PD)` are only meant to be used by integrations with third party site uptime monitoring services.
 * They have no functionality in the limited uptime monitoring provided by Panopticon itself.
 *
 * @since 1.1.0
 */
class UptimeStatus extends AbstractDataShape
{
	protected bool $up = true;

	protected ?int $downSince = null;

	protected bool $isScheduled = false;

	protected ?string $detailsUrl = null;
}