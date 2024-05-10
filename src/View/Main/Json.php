<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Main;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\JoomlaUpdateRunState;
use Akeeba\Panopticon\Library\JoomlaVersion\JoomlaVersion;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\AkeebaBackupTooOldTrait;
use Awf\Mvc\DataModel\Collection;
use Awf\Mvc\DataView\Json as BaseView;

class Json extends BaseView
{
	use AkeebaBackupTooOldTrait;

	protected function onBeforeSites()
	{
		// Load the model
		/** @var Site $model */
		$model = $this->getModel();
		$model->setState('enabled', 1);

		// We want to persist the state in the session
		$model->savestate(1);

		$order      = $model->getState('filter_order', 'name', 'cmd');
		$order_Dir  = $model->getState('filter_order_Dir', 'ASC', 'cmd');
		$limitStart = $model->getState('limitstart', 0, 'int');
		$limit      = $model->getState('limit', 50, 'int');

		$model->setState('filter_order', $order);
		$model->setState('filter_order_Dir', $order_Dir);
		$model->setState('limitstart', $limitStart);
		$model->setState('limit', $limit);

		/** @var Collection<Site> $items */
		$items = $model->get();

		$items = $items->map(
			function (Site $site) {
				$siteConfig      = $site->getConfig();
				$cmsType         = $site->cmsType();
				$extensionsList  = $siteConfig->get('extensions.list', new \stdClass());
				$extensions      = get_object_vars($extensionsList);
				$currentVersion  = $siteConfig->get('core.current.version');
				$latestVersion   = $siteConfig->get('core.latest.version');
				$eol             = false;
				$cmsUpdateStatus = 0;
				$extUpdateStatus = 0;
				$numExtUpdates   = array_reduce(
					$extensions,
					function (int $carry, object $item): int {
						$current = $item?->version?->current;
						$new     = $item?->version?->new;

						if (empty($new))
						{
							return $carry;
						}

						return $carry + ((empty($current) || version_compare($current, $new, 'ge')) ? 0 : 1);
					},
					0
				);

				if ($site->isExtensionsUpdateTaskStuck())
				{
					$extUpdateStatus = 3;
				}
				elseif ($site->isExtensionsUpdateTaskRunning())
				{
					$extUpdateStatus = 2;
				}
				elseif ($site->isExtensionsUpdateTaskScheduled())
				{
					$extUpdateStatus = 1;
				}

				switch ($cmsType->value)
				{
					case 'joomla':
						$jVersionHelper  = new JoomlaVersion($this->getContainer());
						$eol             = empty($currentVersion)
						                   || $jVersionHelper->isEOLMajor($currentVersion)
						                   || $jVersionHelper->isEOLBranch($currentVersion);
						$cmsUpdateStatus = match ($site->getJoomlaUpdateRunState())
						{
							default => 0,
							JoomlaUpdateRunState::SCHEDULED => 1,
							JoomlaUpdateRunState::RUNNING => 2,
							JoomlaUpdateRunState::ERROR => 3,
						};

						break;

					case 'wordpress':
						// TODO
						break;
				}

				return [
					'id'                => $site->getId(),
					'name'              => $site->name,
					'favicon'           => $site->getFavicon(asDataUrl: true, onlyIfCached: true),
					'url'               => $site->getBaseUrl(),
					'overview_url'      => $this->getContainer()->router->route(
						sprintf('index.php?view=Site&task=read&id=%d', $site->getId())
					),
					'groups'            => $site->getGroups(true),
					'cms'               => $cmsType->value,
					'version'           => $currentVersion,
					'eol'               => $eol,
					'latest'            => ($currentVersion === $latestVersion) ? null : $latestVersion,
					'php'               => $siteConfig->get('core.php', '0.0.0') ?: '0.0.0',
					'extensions'        => $numExtUpdates,
					'overrides'         => $siteConfig->get('core.overridesChanged') ?: 0,
					'errors'            => [
						'site'       => trim($siteConfig->get('core.lastErrorMessage') ?? '') ?: null,
						'extensions' => trim($siteConfig->get('extensions.lastErrorMessage') ?? '') ?: null,
					],
					'updating'          => [
						'cms'        => $cmsUpdateStatus,
						'extensions' => $extUpdateStatus,
					],
					'certificateStatus' => $site->getSSLValidityStatus(),
					'backup'            => [
						'isInstalled' => $site->hasAkeebaBackup()
						                 || $siteConfig->get(
								'akeebabackup.info.installed', false
							),
						'isPro'       => !empty($siteConfig->get('akeebabackup.info.api')),
						'noRecord'    => empty($siteConfig->get('akeebabackup.latest')),
						'meta'        => $siteConfig->get('akeebabackup.latest')?->meta ?? null,
						'tooOld'      => $this->isTooOldBackup($siteConfig->get('akeebabackup.latest'), $siteConfig),
					],
					'uptime'            => $this->getContainer()->helper->uptime->status($site)->asArray(),
				];
			}
		);

		$document = $this->container->application->getDocument();
		$document->setUseHashes(true);
		$document->setMimeType('application/json');
		$document->setName(null);

		echo json_encode($items->toArray(), JSON_PRETTY_PRINT);

		return true;
	}
}