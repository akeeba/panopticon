<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Plugin;


use Akeeba\Panopticon\Library\Uptime\UptimeStatus;
use Akeeba\Panopticon\Model\Site;

defined('AKEEBA') || die;

abstract class AbstractUptimePlugin extends PanopticonPlugin
{
	/**
	 * Returns the events handled by this plugin
	 *
	 * @return  string[]
	 * @since   1.1.0
	 */
	public function getObservableEvents(): array
	{
		return [
			'onGetUptimeProvider',
			'onSiteIsBackUp',
			'onSiteHasGoneDown',
			'onSiteGetUptimeStatus',
		];
	}

	/**
	 * Returns the name and translation key for this uptime provider
	 *
	 * @return  array
	 * @since   1.1.0
	 */
	abstract public function onGetUptimeProvider(): array;

	/**
	 * Called when the site is back on-line
	 *
	 * @param   Site      $site       The site object
	 * @param   int|null  $downSince  When did the site was first detected as being down?
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	abstract public function onSiteIsBackUp(Site $site, ?int $downSince): void;

	/**
	 * Called when the site goes down
	 *
	 * @param   Site  $site  The site object
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	abstract public function onSiteHasGoneDown(Site $site): void;

	/**
	 * Returns the uptime status of the site
	 *
	 * @param   Site  $site
	 *
	 * @return  UptimeStatus|null
	 * @since   1.1.0
	 */
	abstract public function onSiteGetUptimeStatus(Site $site): ?UptimeStatus;
}