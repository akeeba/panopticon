<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Uptime\UptimeStatus;
use Akeeba\Panopticon\Model\Site;
use Awf\Helper\AbstractHelper;

/**
 * Helper methods for uptime monitoring integration.
 *
 * @since 1.1.0
 */
class Uptime extends AbstractHelper
{
	public function status(Site $site): UptimeStatus
	{
		$container = Factory::getContainer();
		$results   = $container->eventDispatcher->trigger('onSiteGetUptimeStatus', [$site]);

		foreach ($results as $result)
		{
			if ($result instanceof UptimeStatus)
			{
				return $result;
			}
		}

		return new UptimeStatus();
	}
}