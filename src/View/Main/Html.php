<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Main;

use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;
use Akeeba\Panopticon\Library\SelfUpdate\UpdateInformation;
use Akeeba\Panopticon\Library\SelfUpdate\VersionInformation;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Groups;
use Akeeba\Panopticon\Model\Selfupdate;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Usagestats;
use Awf\Pagination\Pagination;
use Awf\Registry\Registry;
use Awf\Utils\Template;

defined('AKEEBA') || die;

class Html extends \Awf\Mvc\DataView\Html
{
	/**
	 * The proposed key for web-based pseudo-CRON.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public string $cronKey;

	/**
	 * How many seconds are the CRON tasks falling behind?
	 *
	 * @var   int|null
	 * @since 1.0.5
	 */
	public ?int $cronSecondsBehind = 0;

	/**
	 * The groups currently used in sites.
	 *
	 * @var   array
	 * @since 1.0.5
	 */
	public array $groupMap = [];

	/**
	 * The currently logged-in user.
	 *
	 * @var   User|null
	 * @since 1.0.6
	 */
	public ?User $user = null;

	/**
	 * Can the current user create sites?
	 *
	 * @var   bool
	 * @since 1.0.6
	 */
	public bool $canCreate = false;

	/**
	 * Is the sites list filtered?
	 *
	 * @var   bool
	 * @since 1.0.6
	 */
	public bool $isFiltered = false;

	/**
	 * The update information for Panopticon itself.
	 *
	 * @var   UpdateInformation $selfUpdateInformation
	 * @since 1.0.6
	 */
	public UpdateInformation $selfUpdateInformation;

	/**
	 * Does Panopticon have a pending update for itself?
	 *
	 * @var   bool $hasSelfUpdate
	 * @since 1.0.6
	 */
	public bool $hasSelfUpdate;

	/**
	 * The latest version of Panopticon.
	 *
	 * @var   string $latestPanopticonVersion
	 * @since 1.0.6
	 */
	public ?VersionInformation $latestPanopticonVersion;

	protected function onBeforeMain()
	{
		$isDashboard = $this->getLayout() === 'dashboard';
		$app         = $this->getContainer()->application;
		$doc         = $app->getDocument();
		$router      = $this->getContainer()->router;
		$appConfig   = $this->getContainer()->appConfig;

		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		// Groups map
		/** @var Groups $groupsModel */
		$groupsModel    = $this->getModel('groups');
		$this->groupMap = $groupsModel->getGroupMap();

		// Create the lists object
		$this->lists = new \stdClass();

		// Load the model
		/** @var Site $model */
		$model = $this->getModel();
		$model->setState('enabled', 1);

		// We want to persist the state in the session
		$model->savestate(1);

		// Ordering information
		$this->lists->order     = $model->getState('filter_order', 'name', 'cmd');
		$this->lists->order_Dir = $model->getState('filter_order_Dir', 'ASC', 'cmd');

		// Display limits
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('limit', 50, 'int');

		$model->setState('filter_order', $this->lists->order);
		$model->setState('filter_order_Dir', $this->lists->order_Dir);
		$model->setState('limitstart', $this->lists->limitStart);
		$model->setState('limit', $this->lists->limit);

		// How far behind are the CRON jobs?
		$this->cronSecondsBehind = $this->getContainer()->mvcFactory->makeTempModel('main')
			->getCRONJobsSecondsBehind();

		// Assign items to the view
		$this->itemsCount = $model->count();

		if (!$isDashboard)
		{
			$this->items      = $model->get();

			// Pagination
			$displayedLinks   = 10;
			$this->pagination = new Pagination(
				$this->itemsCount, $this->lists->limitStart, $this->lists->limit, $displayedLinks, $this->container
			);
		}

		// Assign other information to the view
		$this->user           = $this->getContainer()->userManager->getUser();
		$this->canCreate      = $this->user->getPrivilege('panopticon.admin')
		                        || $this->user->getPrivilege('panopticon.addown');
		$this->isFiltered     = array_reduce(
			['search', 'coreUpdates', 'extUpdates', 'phpFamily', 'cmsFamily'],
			fn(bool $carry, string $filterKey) => $carry || $model->getState($filterKey) !== null,
			false
		);
		$this->phpVersionInfo = $appConfig->get('phpwarnings', true)
			? (new PhpVersion())->getVersionInformation(PHP_VERSION)
			: null;

		// Self-update information
		/** @var Selfupdate $selfUpdateModel */
		$selfUpdateModel               = $this->getModel('selfupdate');
		$this->selfUpdateInformation   = $selfUpdateModel->getUpdateInformation();
		$this->hasSelfUpdate           = $selfUpdateModel->hasUpdate();
		$this->latestPanopticonVersion = $selfUpdateModel->getLatestVersion();

		// Back button in the CRON instructions page
		if ($this->layout === 'cron')
		{
			$this->cronKey = $appConfig->get('webcron_key', '');

			$doc->getToolbar()->addButtonFromDefinition(
				[
					'id'    => 'prev',
					'title' => $this->getLanguage()->text('PANOPTICON_BTN_PREV'),
					'class' => 'btn btn-secondary border-light',
					'url'   => $router->route('index.php'),
					'icon'  => 'fa fa-chevron-left',
				]
			);
		}

		// JavaScript
		/** @var Usagestats $usageStatsModel */
		$usageStatsModel = $this->getModel('usagestats');

		$doc->addScriptOptions(
			'panopticon.heartbeat', [
				'url'       => $router->route('index.php?view=main&task=heartbeat&format=json'),
				'warningId' => 'heartbeatWarning',
			]
		);
		$doc->addScriptOptions(
			'panopticon.usagestats', [
				'url'     => $router->route('index.php?view=usagestats&task=ajax&format=raw'),
				'enabled' => $usageStatsModel->isStatsCollectionEnabled(),
			]
		);

		// DO NOT TRANSPOSE THESE LINES. Choices.js needs to be loaded before our main.js.
		Template::addJs('media://choices/choices.min.js', $app, defer: true);
		Template::addJs('media://js/main.js', $app, defer: true);

		if ($isDashboard)
		{
			$doc->addScript(\Awf\Utils\Template::parsePath('axios/axios.js', false, $app), defer: true);
			$doc->addScript(\Awf\Utils\Template::parsePath('js/main-dashboard.js', false, $app), type: 'module');
			$doc->addScriptOptions(
				'panopticon.dashboard',
				[
					'url'       => $router->route(
						'index.php?view=main&task=sites&format=json&' . $this->getContainer()->session->getCsrfToken()
							->getValue() . '=1'
					),
					'maxTimer'  => $appConfig->get('dashboard_reload_timer', 90),
					'maxPages'  => $appConfig->get('dashboard_max_pages', 50),
					'pageLimit' => $appConfig->get('dashboard_page_limit', 20),
				]
			);

		}

		// Toolbar
		$toolbar = $doc->getToolbar();
		$toolbar->setTitle($this->getLanguage()->text('PANOPTICON_MAIN_TITLE'));
		$toolbar->addButtonFromDefinition(
			[
				'id'    => 'manageSites',
				'title' => $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_MY_SITES_MANAGE'),
				'class' => 'btn btn-secondary border-light',
				'url'   => $router->route('index.php?view=sites'),
				'icon'  => 'fa fa-gears',
			]
		);

		return true;
	}

	/**
	 * Retrieves the extensions from the given site configuration.
	 *
	 * @param   Registry  $config  The Registry object containing the site configuration.
	 *
	 * @return  array  An array of extensions.
	 * @since   1.0.6
	 */
	protected function getExtensions(Registry $config): array
	{
		return get_object_vars($config->get('extensions.list', new \stdClass()));
	}

	/**
	 * Retrieves the number of extensions that have updates available.
	 *
	 * @param   array  $extensions  An array of extension objects.
	 *
	 * @return  int  The number of extensions with available updates.
	 * @since   1.0.6
	 */
	protected function getNumberOfExtensionUpdates(array $extensions): int
	{
		return array_reduce(
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
	}

	/**
	 * Retrieves the number of extensions with missing Download Keys.
	 *
	 * @param   array  $extensions  An array of objects representing the extensions.
	 *
	 * @return  int The number of extensions with missing Download Keys.
	 * @since   1.0.6
	 */
	protected function getNumberOfKeyMissingExtensions(array $extensions): int
	{
		return array_reduce(
			$extensions,
			fn(int $carry, object $item): int => $carry + (
				!$item?->downloadkey?->supported || $item?->downloadkey?->valid ? 0 : 1
				),
			0
		);
	}

	/**
	 * Retrieves the last error message from the extensions update process.
	 *
	 * @param   Registry  $config  The site configuration registry object.
	 *
	 * @return  string  The last error message from the extensions update process. Returns an empty string if no errors
	 *                  occurred.
	 * @since   1.0.6
	 */
	protected function getLastExtensionsUpdateError(Registry $config): string
	{
		return trim($config->get('extensions.lastErrorMessage') ?? '');
	}
}