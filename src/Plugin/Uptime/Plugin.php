<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Plugin\Uptime;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Sites;
use Akeeba\Panopticon\Library\Plugin\AbstractUptimePlugin;
use Akeeba\Panopticon\Library\Uptime\UptimeStatus;
use Akeeba\Panopticon\Model\Site;
use Awf\Container\Container;
use Awf\Event\Observable;
use Awf\Mvc\Controller;

class Plugin extends AbstractUptimePlugin
{
	private static ?bool $isEnabled = null;

	/** @inheritDoc */
	public function __construct(Observable &$subject, Container $container)
	{
		$this->name = 'Uptime';

		parent::__construct($subject, $container);
	}

	/** @inheritDoc */
	public function getObservableEvents(): array
	{
		// If a different uptime provider is used don't bother.
		if ($this->getContainer()->appConfig->get('uptime', 'none') !== 'panopticon')
		{
			return [
				'onGetUptimeProvider'
			];
		}

		// We add some extra events we need to handle for per-site configuration.
		return array_merge(
			parent::getObservableEvents(),
			[
				'onSiteDisplayAddEdit',
				'onControllerBeforeExecute'
			]
		);
	}

	/** @inheritDoc */
	public function onGetUptimeProvider(): array
	{
		return ['panopticon' => 'PANOPTICON_SYSCONFIG_OPT_UPTIME_PANOPTICON'];
	}

	/** @inheritDoc */
	public function onSiteGetUptimeStatus(Site $site): ?UptimeStatus
	{
		$downSince = $site->getConfig()->get('uptime.downtime_start', null);
		$isUp      = !($downSince !== null && (int) $downSince > 0);

		return new UptimeStatus(
			[
				'up'        => $isUp,
				'downSince' => $isUp ? null : (int) $downSince,
			]
		);
	}

	public function onControllerBeforeExecute(Controller $controller, string $task): bool
	{
		// Make sure it's the controller and task indicating a site is being saved.
		if (!$controller instanceof Sites || !in_array($task, ['save', 'apply']))
		{
			return true;
		}

		// Get the 'config' input key which, watch out, is an ARRAY.
		$input  = $controller->getContainer()->input;
		$config = $input->get('config', [], 'array');

		// Sanity check.
		if (empty($config) || !is_array($config))
		{
			return true;
		}

		// Toggles generate no form value when they are disabled. Therefore, we have to use this server-side hack.
		$config['uptime.enable'] = isset($config['uptime.enable']);

		$input->set('config', $config);

		// All good. Carry on, good sir.
		return true;
	}

	/**
	 * Outputs custom HTML in the Other Features tab of the edit site page.
	 *
	 * @return  string
	 * @since   1.1.0
	 */
	public function onSiteDisplayAddEdit(Site $site): string
	{
		if (empty($site->url))
		{
			return '';
		}

		return $this->loadTemplate(
			'PluginUptime/edit',
			[
				'site' => $site
			]
		);
	}

	/** @inheritDoc */
	public function onSiteIsBackUp(Site $site, ?int $downSince): void
	{
		// No operation
	}

	/** @inheritDoc */
	public function onSiteHasGoneDown(Site $site): void
	{
		// No operation
	}
}