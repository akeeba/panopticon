<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Enumerations\JoomlaUpdateRunState;
use Akeeba\Panopticon\Library\Toolbar\DropdownButton;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Sysconfig;
use Akeeba\Panopticon\Model\Trait\ExtensionAutoUpdateInfoTrait;
use Akeeba\Panopticon\Model\Trait\FormatFilesizeTrait;
use Akeeba\Panopticon\Task\Trait\AdminToolsTrait;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Date\Date;
use Awf\Document\Toolbar\Button;
use Awf\Mvc\DataView\Html as DataViewHtml;
use Awf\Registry\Registry;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

/**
 * Html class for rendering HTML views related to managing Sites.
 *
 * @since     1.0.0
 */
class Html extends DataViewHtml
{
	use TimeAgoTrait;
	use ShowOnTrait;
	use CrudTasksTrait
	{
		onBeforeBrowse as onBeforeBrowseCrud;
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}
	use AdminToolsTrait;
	use FormatFilesizeTrait;
	use ExtensionAutoUpdateInfoTrait;

	public object $extension;

	public array|Throwable $backupRecords;

	public array $groupMap = [];

	public string|Throwable|null $connectionError = null;

	public Throwable|null $akeebaBackupConnectionError = null;

	protected Site $item;

	protected array $extUpdatePreferences = [];

	protected array $globalExtUpdatePreferences = [];

	protected string $defaultExtUpdatePreference = 'none';

	protected bool $hasAdminTools = false;

	protected bool $hasAdminToolsPro = false;

	protected array|Throwable $scans = [];

	protected array $extensionFilters = [
		'filter-updatesite'  => 'fa-globe',
		'filter-dlid'        => 'fa-key',
		'filter-naughty'     => 'fa-bug',
		'filter-scheduled'   => 'fa-hourglass-half',
		'filter-unscheduled' => 'fa-bolt',
	];

	/**
	 * Determines if the user has the permission to edit the current site.
	 *
	 * @var   bool
	 * @since 1.0.6
	 */
	protected bool $canEdit = false;

	protected ?DateTime $cronStuckTime = null;

	/**
	 * The configuration settings for the current site.
	 *
	 * @var   Registry
	 * @since 1.0.6
	 */
	protected Registry $siteConfig;

	/**
	 * The version of the Panopticon connector we're talking to.
	 *
	 * @var   string|null
	 * @since 1.0.6
	 */
	protected ?string $connectorVersion;

	/**
	 * The API level of the Panopticon connector.
	 *
	 * @var   string|null
	 * @since 1.0.6
	 */
	protected ?string $connectorAPI;

	/**
	 * The base URI of the site.
	 *
	 * This variable stores the base (public) URI of the site, which is used to construct the full URL for redirecting
	 * or generating links.
	 *
	 * @var   Uri
	 * @since 1.0.6
	 */
	protected Uri $baseUri;

	/**
	 * The URI of the site's admin panel login page.
	 *
	 * This variable stores the URI (Uniform Resource Identifier) of the admin panel login page.
	 *
	 * @var   Uri
	 * @since 1.0.6
	 */
	protected Uri $adminUri;

	/**
	 * The state of whether the Joomla update task is running or not.
	 *
	 * @var   JoomlaUpdateRunState
	 * @since 1.0.6
	 */
	protected JoomlaUpdateRunState $joomlaUpdateRunState;

	/**
	 * Holds an array of extensions installed on the site.
	 *
	 * @var   array
	 * @since 1.0.6
	 */
	protected array $extensions;

	private ?string $curlError = null;

	private ?string $guzzleError = null;

	private ?int $httpCode;

	private array $backupProfiles = [];

	public function onBeforeConnectionDoctor(): bool
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_SITES_LBL_CONNECTION_DOCTOR_TITLE'));
		$this->addButton(
			'back', [
				'url' => $this->container->router->route(
					sprintf('index.php?view=site&task=read&id=%d', $this->getModel()->getId())
				),
			]
		);

		$this->setLayout('doctor');

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item            = $this->getModel();
		$this->canEdit         = $this->item->canEdit();
		$this->connectionError = $this->container->segment->getFlash('site_connection_error', $this->connectionError) ??
		                         $this->connectionError;
		$this->httpCode        = $this->container->segment->getFlash('site_connection_http_code', null);
		$this->curlError       = $this->container->segment->getFlash('site_connection_curl_error', null);
		$this->guzzleError     = $this->container->segment->getFlash('site_connection_guzzle_error', null);

		return true;
	}

	public function onBeforeDlkey(): bool
	{
		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->setTitle($this->getLanguage()->text('PANOPTICON_SITES_LBL_DLKEY_EDIT_TITLE'));
		$this->addButton(
			'back', [
				'url' => $this->container->router->route(
					sprintf('index.php?view=site&task=read&id=%d', $this->getModel()->getId())
				),
			]
		);
		$this->addButton('save', ['task' => 'savedlkey']);

		return true;
	}

	public function onBeforeBrowse(): bool
	{
		Template::addJs('media://choices/choices.min.js', $this->getContainer()->application, defer: true);

		$result = $this->onBeforeBrowseCrud();

		// Groups map
		$this->groupMap = $this->getModel('groups')->getGroupMap();

		$user      = $this->container->userManager->getUser();
		$canAdd    = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.addown');
		$canEdit   = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.editown');
		$canDelete = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.editown');
		$buttons   = [];
		$buttons[] = $canAdd ? 'add' : null;
		$buttons[] = $canEdit ? 'edit' : null;
		$buttons[] = $canDelete ? 'delete' : null;

		$this->container->application->getDocument()->getToolbar()->clearButtons();
		$this->addButtons($buttons);

		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);
		Template::addJs('media://js/site-browse.js', $this->getContainer()->application);

		$this->addTooltipJavaScript();

		return $result;
	}

	public function getProfileOptions(): array
	{
		static $profiles = null;

		$useCache = !$this->item->getState('akeebaBackupForce', false, 'bool');
		$profiles ??= $this->getModel()->akeebaBackupGetProfiles($useCache);
		$ret      = [];

		foreach ($profiles as $profile)
		{
			$ret[$profile->id] = (object) [
				'value' => $profile->id,
				'text'  => sprintf('#%u. %s', $profile->id, $profile->name),
			];
		}

		return $ret;
	}

	public function onBeforeAdd()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);
		Template::addJs('media://choices/choices.min.js', $this->getContainer()->application, defer: true);

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		$this->connectionError = $this->container->segment->getFlash('site_connection_error', null);
		$this->httpCode        = $this->container->segment->getFlash('site_connection_http_code', null);
		$this->curlError       = $this->container->segment->getFlash('site_connection_curl_error', null);
		$this->guzzleError     = $this->container->segment->getFlash('site_connection_guzzle_error', null);

		$document = $this->container->application->getDocument();
		$document->addScriptOptions(
			'panopticon.rememberTab', [
				'key' => 'panopticon.siteAdd.rememberTab',
			]
		);
		$document->addScriptOptions(
			'panopticon.backupOnUpdate', [
				'reload'  => $this->getContainer()->router->route(
					'index.php?view=site&task=reloadBoU&id=' . $this->item->getId() . '&extensions=1&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
				'relink'  => $this->getContainer()->router->route(
					'index.php?view=site&task=reloadBoU&id=' . $this->item->getId() . '&relink=1&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
				'refresh' => $this->getContainer()->router->route(
					'index.php?view=site&task=akeebaBackupProfilesSelect&id=' . $this->item->getId() . '&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
			]
		);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);
		Template::addJs('media://js/site-edit.js', $this->getContainer()->application);

		return $this->onBeforeAddCrud();
	}

	public function onBeforeEdit()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);
		Template::addJs('media://choices/choices.min.js', $this->getContainer()->application, defer: true);

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		/** @var Sysconfig $sysConfigModel */
		$sysConfigModel                   = $this->getModel('Sysconfig');
		$this->extUpdatePreferences       = $sysConfigModel->getExtensionPreferencesAndMeta($this->item->id);
		$this->globalExtUpdatePreferences = $sysConfigModel->getExtensionPreferencesAndMeta(null);
		$this->defaultExtUpdatePreference = $this->container->appConfig->get('tasks_extupdate_install', 'none');

		$this->connectionError = $this->container->segment->getFlash('site_connection_error', null);
		$this->httpCode        = $this->container->segment->getFlash('site_connection_http_code', null);
		$this->curlError       = $this->container->segment->getFlash('site_connection_curl_error', null);
		$this->guzzleError     = $this->container->segment->getFlash('site_connection_guzzle_error', null);

		$document = $this->container->application->getDocument();
		$document->addScriptOptions(
			'panopticon.rememberTab', [
				'key' => 'panopticon.siteEdit.' . $this->getModel()->id . '.rememberTab',
			]
		);
		$document->addScriptOptions(
			'panopticon.backupOnUpdate', [
				'reload'  => $this->getContainer()->router->route(
					'index.php?view=site&task=reloadBoU&id=' . $this->item->getId() . '&extensions=1&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
				'relink'  => $this->getContainer()->router->route(
					'index.php?view=site&task=reloadBoU&id=' . $this->item->getId() . '&relink=1&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
				'refresh' => $this->getContainer()->router->route(
					'index.php?view=site&task=akeebaBackupProfilesSelect&id=' . $this->item->getId() . '&format=raw&'
					. $this->getContainer()->session->getCsrfToken()->getValue() . '=1'
				),
			]
		);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);
		Template::addJs('media://js/site-edit.js', $this->getContainer()->application);

		return $this->onBeforeEditCrud();
	}

	public function onBeforeRead(): bool
	{
		Template::addJs('media://js/site-read.js', $this->getContainer()->application, defer: true);

		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$router = $this->container->router;
		$this->addButton('back', ['url' => $router->route('index.php?view=main')]);

		$this->setTitle($this->getLanguage()->text('PANOPTICON_SITES_TITLE_READ'));

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item                = $this->getModel();
		$this->canEdit             = $this->item->canEdit();
		$this->siteConfig          = $this->item->getConfig();
		$this->connectorVersion    = $this->siteConfig->get('core.panopticon.version');
		$this->connectorAPI        = $this->siteConfig->get('core.panopticon.api');
		$this->baseUri             = Uri::getInstance($this->item->getBaseUrl());
		$this->adminUri            = Uri::getInstance($this->item->getAdminUrl());
		$this->extensions          = $this->item->getExtensionsList();

		if ($this->item->cmsType() === CMSType::JOOMLA)
		{
			$this->joomlaUpdateRunState = $this->item->getJoomlaUpdateRunState();
		}

		try
		{
			$useCache             = !$this->item->getState('akeebaBackupForce', false, 'bool');
			$this->backupRecords  = $this->item->akeebaBackupGetBackups(
				$useCache, $this->item->getState('akeebaBackupFrom', 0, 'int'),
				$this->item->getState('akeebaBackupLimit', 20, 'int'),
			);
			$this->backupProfiles = $this->item->akeebaBackupGetProfiles($useCache);
		}
		catch (Throwable $e)
		{
			$this->backupRecords = $e;
		}

		$this->hasAdminTools    = $this->hasAdminTools($this->item, false);
		$this->hasAdminToolsPro = $this->hasAdminTools($this->item, true);

		if ($this->hasAdminToolsPro)
		{
			try
			{
				$useCache    = !$this->item->getState('adminToolsForce', false, 'bool');
				$this->scans = $this->getModel()->adminToolsGetScans(
					$useCache, $this->item->getState('adminToolsFrom', 0, 'int'),
					$this->item->getState('adminToolsLimit', 20, 'int'),
				)?->items ?? [];
			}
			catch (Exception $e)
			{
				$this->scans = $e;
			}
		}

		$hasAkeebaBackup   = $this->item->hasAkeebaBackup();
		$hasAkeebaSoftware = $hasAkeebaBackup || $this->hasAdminToolsPro;

		$dropdown = (new DropdownButton(
			[
				'id'    => 'dropdown-automations',
				'icon'  => 'fa fa-fw fa-bolt-lightning',
				'title' => $this->getContainer()->language->text('PANOPTICON_SITES_LBL_DROPDOWN_AUTOMATIONS'),
				'class' => 'btn btn-info ms-2',
			]
		))->addButton(
			new Button(
				[
					'class' => 'header',
					'title' => $this->getContainer()->language->text(
						'PANOPTICON_SITES_LBL_DROPDOWN_AUTOMATIONS_HEAD_EMAILS'
					),
				]
			)
		)->addButton(
			new Button(
				[
					'id'    => 'updatesummarytasks',
					'icon'  => 'fa fa-fw fa-envelope',
					'title' => $this->getContainer()->language->text('PANOPTICON_UPDATESUMMARYTASKS_TITLE'),
					'url'   => $router->route(
						sprintf("index.php?view=updatesummarytasks&site_id=%s", $this->item->getId())
					),
				]
			)
		)
//			->addButton(
//				new Button(
//					[
//						'id'    => 'actionsummarytasks',
//						'icon'  => 'fa fa-fw fa-envelope-open-text',
//						'title' => 'Scheduled Action Summary',
//						'url'   => $router->route(
//							sprintf("index.php?view=actionsummarytasks&site_id=%s", $this->item->getId())
//						),
//					]
//				)
//			)
			->addButton(
				new Button(
					[
						'class' => 'header ' . ($hasAkeebaSoftware ? '' : 'd-none'),
						'title' => $this->getContainer()->language->text(
							'PANOPTICON_SITES_LBL_DROPDOWN_AUTOMATIONS_HEAD_BACKUP_SECURITY'
						),
					]
				)
			)->addButton(
				new Button(
					[
						'id'    => 'backuptasks',
						'icon'  => 'fa fa-fw fa-hard-drive',
						'title' => $this->getContainer()->language->text('PANOPTICON_SITES_LBL_AKEEBABACKUP_SCHEDULE'),
						'url'   => $router->route(
							sprintf("index.php?view=backuptasks&site_id=%s&manual=0", $this->item->getId())
						),
					]
				)
			)->addButton(
				new Button(
					[
						'id'    => 'scannertasks',
						'icon'  => 'fa fa-fw fa-shield-halved',
						'title' => $this->getContainer()->language->text('PANOPTICON_SITES_LBL_ADMINTOOLS_SCHEDULE'),
						'url'   => $router->route(
							sprintf("index.php?view=scannertasks&site_id=%s&manual=0", $this->item->getId())
						),
					]
				)
			);

		$this->container->application->getDocument()->getToolbar()->addButton($dropdown);

		if ($this->canEdit)
		{
			$this->addButtonFromDefinition(
				[
					'id'    => 'doctor',
					'title' => $this->getLanguage()->text('PANOPTICON_SITES_LBL_CONNECTION_DOCTOR_TITLE'),
					'class' => 'btn btn-secondary border-light',
					'url'   => $router->route(
						sprintf("index.php?view=site&task=connectionDoctor&id=%s", $this->item->getId())
					),
					'icon'  => 'fa fa-fw fa-stethoscope',
				]
			);
		}

		$this->cronStuckTime = $this->getCronStuckTime();

		$document = $this->container->application->getDocument();

		$document->addScriptOptions(
			'panopticon.rememberTab', [
				'key' => 'panopticon.siteRead.' . $this->getModel()->id . '.rememberTab',
			]
		);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		$document->addScriptOptions(
			'panopticon.siteRemember', [
				'extensionsFilters' => sprintf('panopticon.site%d.extensionFilters', $this->item->getId()),
				'collapsible'       => sprintf('panopticon.site%d.collapsible', $this->item->getId()),
			]
		);

		$this->addTooltipJavaScript();

		$document->addScriptOptions(
			'akeebabackup', [
				'enqueue' => $router->route(
					sprintf(
						'index.php?view=sites&task=akeebaBackupEnqueue&id=%d&%s=1', $this->item->id,
						$this->container->session->getCsrfToken()->getValue()
					)
				),
			]
		);

		return true;
	}

	/**
	 * Formats a date using a specified date format key.
	 *
	 * @param   string|DateTime|Date  $date       The date to format
	 * @param   string                $formatKey  The key to retrieve the date format from language
	 *                                            (default: 'DATE_FORMAT_LC1')
	 *
	 * @return  string  The formatted date
	 * @since   1.0.6
	 */
	protected function formatDate(string|DateTime|Date $date, string $formatKey = 'DATE_FORMAT_LC1'): string
	{
		if (is_numeric($date) && trim($date) == intval($date))
		{
			try
			{
				$date = new DateTime('@' . intval($date));
			}
			catch (Exception)
			{
				return '';
			}
		}

		if ($date instanceof DateTime)
		{
			$date = $date->format(DATE_ATOM);
		}

		return $this->getContainer()->html->basic->date($date, $this->getLanguage()->text($formatKey));
	}

	/**
	 * Returns the origin's translated name and the appropriate icon class
	 *
	 * @param   object  $record  A backup record
	 *
	 * @return  array  array(originTranslation, iconClass)
	 * @since   1.0.0
	 */
	protected function getOriginInformation(object $record): array
	{
		$originLanguageKey = 'PANOPTICON_SITES_LBL_AKEEBABACKUP_ORIGIN_' . ($record?->origin ?? '');
		$originDescription = $this->getLanguage()->text($originLanguageKey);

		switch (strtolower($record?->origin ?? ''))
		{
			case 'backend':
				$originIcon = 'fa fa-desktop';
				break;

			case 'frontend':
				$originIcon = 'fa fa-globe';
				break;

			case 'json':
				$originIcon = 'fa fa-cloud';
				break;

			case 'joomlacli':
			case 'joomla':
				$originIcon = 'fa fab fa-joomla';
				break;

			case 'cli':
				$originIcon = 'fa fa-terminal';
				break;

			case 'wpcron':
				$originIcon = 'fab fa-wordpress';
				break;

			case 'xmlrpc':
				$originIcon = 'fa fa-code';
				break;

			case 'lazy':
				$originIcon = 'fa fa-cubes';
				break;

			default:
				$originIcon = 'fa fa-question';
				break;
		}

		if (empty($originLanguageKey) || ($originDescription == $originLanguageKey))
		{
			$originDescription = $this->getLanguage()->text('PANOPTICON_SITES_LBL_AKEEBABACKUP_ORIGIN_UNKNOWN');
			$originIcon        = 'fa fa-question-circle';

			return [$originDescription, $originIcon];
		}

		return [$originDescription, $originIcon];
	}

	/**
	 * Get the start time and duration of a backup record
	 *
	 * @param   object  $record  A backup record
	 *
	 * @return  array  array(startTimeAsString, durationAsString)
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function getTimeInformation(object $record): array
	{
		$utcTimeZone = new DateTimeZone('UTC');

		try
		{
			$startTime = clone $this->container->dateFactory($record->backupstart, $utcTimeZone);
		}
		catch (Exception $e)
		{
			$startTime = null;
		}

		try
		{
			$endTime = clone $this->container->dateFactory($record->backupend, $utcTimeZone);
		}
		catch (Exception $e)
		{
			$endTime = null;
		}

		$duration = (is_null($startTime) || is_null($endTime)) ? 0 : $endTime->toUnix() - $startTime->toUnix();

		if ($duration > 0)
		{
			$seconds  = $duration % 60;
			$duration = $duration - $seconds;

			$minutes  = ($duration % 3600) / 60;
			$duration = $duration - $minutes * 60;

			$hours    = $duration / 3600;
			$duration = sprintf('%02d', $hours) . ':' . sprintf('%02d', $minutes) . ':' . sprintf('%02d', $seconds);
		}
		else
		{
			$duration = '';
		}

		$tz = new DateTimeZone($this->container->appConfig->get('timezone', 'UTC'));

		if ($startTime !== null)
		{
			$startTime->setTimezone($tz);
		}

		$timeZoneSuffix = $startTime->format('T', true);

		return [
			is_null($startTime) ? '&nbsp;' : $startTime->format($this->getLanguage()->text('DATE_FORMAT_LC6'), true),
			$duration,
			$timeZoneSuffix,
		];
	}

	/**
	 * Get the class and icon for the backup status indicator
	 *
	 * @param   object  $record  A backup record
	 *
	 * @return  array  array(class, icon)
	 * @since   1.0.0
	 */
	protected function getStatusInformation(object $record): array
	{
		switch ($record->meta)
		{
			case 'ok':
				$statusIcon  = 'fa fa-check-circle';
				$statusClass = 'bg-success';
				break;
			case 'pending':
				$statusIcon  = 'fa fa-play';
				$statusClass = 'bg-warning';
				break;
			case 'fail':
				$statusIcon  = 'fa fa-times';
				$statusClass = 'bg-danger';
				break;
			case 'remote':
				$statusIcon  = 'fa fa-cloud';
				$statusClass = 'bg-primary';
				break;
			default:
				$statusIcon  = 'fa fa-trash';
				$statusClass = 'bg-secondary';
				break;
		}

		return [$statusClass, $statusIcon];
	}

	/**
	 * Get the profile name for the backup record (or "–" if the profile no longer exists)
	 *
	 * @param   object  $record  A backup record
	 *
	 * @return  string
	 */
	protected function getProfileName(object $record): string
	{
		static $profiles = null;

		if (is_null($profiles))
		{
			$profiles = [];

			foreach ($this->backupProfiles as $profileInfo)
			{
				$profiles[$profileInfo->id] = $profileInfo->name;
			}
		}

		return $profiles[$record->profile_id] ?? '—';
	}

	/**
	 * Converts a number of minutes to years, months, days, and HH:MM human notation
	 *
	 * @param   int  $minutes  The number of minutes to parse
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   1.0.5
	 */
	protected function minutesToHumanReadable(int $minutes): string
	{
		$now  = new DateTime();
		$then = (clone $now)->add(new DateInterval('PT' . $minutes . 'M'));
		$diff = $then->diff($now);

		$out = [];

		if ($diff->y)
		{
			$out[] = $this->getLanguage()->plural('PANOPTICON_LBL_YEAR', $diff->y);
		}

		if ($diff->m)
		{
			$out[] = $this->getLanguage()->plural('PANOPTICON_LBL_MONTH', $diff->m);
		}

		if ($diff->d)
		{
			$out[] = $this->getLanguage()->plural('PANOPTICON_LBL_DAY', $diff->d);
		}

		if ($diff->h > 0 || $diff->i > 0)
		{
			$out[] = sprintf('%02u:%02u', $diff->h, $diff->i);
		}

		return implode(', ', $out);
	}

	/**
	 * Does the current site have collected server information?
	 *
	 * @return  bool
	 * @since   1.0.6
	 */
	protected function hasCollectedServerInfo(): bool
	{
		return $this->siteConfig->get('core.panopticon.api') >= 101
		       && $this->siteConfig->get('core.serverInfo')
		       && $this->siteConfig->get('core.serverInfo.collected');
	}

	/**
	 * @return void
	 */
	private function addTooltipJavaScript(): void
	{
		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"],[data-bs-tooltip="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);
	}

	/**
	 * Returns the latest point in time a task may have last run while still enabled before it is considered "stuck".
	 *
	 * @return  DateTime|null
	 * @since   1.0.5
	 */
	private function getCronStuckTime(): ?DateTime
	{
		$threshold = (int) $this->getContainer()->appConfig->get('cron_stuck_threshold', 3);

		if ($threshold <= 0)
		{
			return null;
		}

		try
		{
			$interval = new DateInterval(sprintf('PT%uM', $threshold));
		}
		catch (Exception $e)
		{
			return null;
		}

		$now = new DateTime();

		return $now->sub($interval);
	}
}