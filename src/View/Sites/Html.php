<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Sites;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Sysconfig;
use Akeeba\Panopticon\Task\AdminToolsTrait;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Date\Date;
use Awf\Mvc\DataView\Html as DataViewHtml;
use Awf\Text\Text;
use Awf\Utils\Template;
use DateTimeZone;
use Throwable;

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

	public object $extension;

	public array|Throwable $backupRecords;

	protected Site $item;

	protected ?string $connectionError = null;

	protected ?string $curlError = null;

	protected ?string $guzzleError = null;

	protected ?int $httpCode;

	protected array $extUpdatePreferences = [];

	protected array $globalExtUpdatePreferences = [];

	protected string $defaultExtUpdatePreference = 'none';

	protected bool $hasAdminTools = false;

	protected bool $hasAdminToolsPro = false;

	protected array|Throwable $scans = [];

	private array $backupProfiles = [];

	protected array $extensionFilters = [
		'filter-updatesite'  => 'fa-globe',
		'filter-dlid'        => 'fa-key',
		'filter-naughty'     => 'fa-bug',
		'filter-scheduled'   => 'fa-hourglass-half',
		'filter-unscheduled' => 'fa-bolt',

	];

	public function onBeforeDlkey(): bool
	{
		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->setTitle(Text::_('PANOPTICON_SITES_LBL_DLKEY_EDIT_TITLE'));
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
		$result = $this->onBeforeBrowseCrud();

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

		$this->addTooltipJavaScript();

		return $result;
	}

	/**
	 * Converts number of bytes to a human-readable representation.
	 *
	 * @param   int|null  $sizeInBytes         Size in bytes
	 * @param   int       $decimals            How many decimals should I use? Default: 2
	 * @param   string    $decSeparator        Decimal separator
	 * @param   string    $thousandsSeparator  Thousands grouping character
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function formatFilesize(
		?int $sizeInBytes, int $decimals = 2, string $decSeparator = '.', string $thousandsSeparator = ''
	): string
	{
		if ($sizeInBytes <= 0)
		{
			return '&mdash;';
		}

		$units = ['b', 'KiB', 'MiB', 'GiB', 'TiB'];
		$unit  = floor(log($sizeInBytes, 2) / 10);

		if ($unit == 0)
		{
			$decimals = 0;
		}

		return number_format($sizeInBytes / (1024 ** $unit), $decimals, $decSeparator, $thousandsSeparator) . ' ' .
			$units[$unit];
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

	protected function onBeforeAdd()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

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
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		return $this->onBeforeAddCrud();
	}

	protected function onBeforeEdit()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

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

		$this->container->application->getDocument()
			->addScriptOptions(
				'panopticon.rememberTab', [
					'key' => 'panopticon.siteEdit.' . $this->getModel()->id . '.rememberTab',
				]
			);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		return $this->onBeforeEditCrud();
	}

	protected function onBeforeRead(): bool
	{
		Template::addJs('media://js/site-read.js', $this->getContainer()->application, defer: true);

		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=main')]);

		$this->setTitle(Text::_('PANOPTICON_SITES_TITLE_READ'));

		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->item = $this->getModel();

		try
		{
			$useCache             = !$this->item->getState('akeebaBackupForce', false, 'bool');
			$this->backupRecords  = $this->item->akeebaBackupGetBackups(
				$useCache,
				$this->item->getState('akeebaBackupFrom', 0, 'int'),
				$this->item->getState('akeebaBackupLimit', 20, 'int'),
			);
			$this->backupProfiles = $this->item->akeebaBackupGetProfiles($useCache);
		}
		catch (\Throwable $e)
		{
			$this->backupRecords = $e;
		}

		$this->hasAdminTools    = $this->hasAdminTools($this->item, false);
		$this->hasAdminToolsPro = $this->hasAdminTools($this->item, true);

		if ($this->hasAdminToolsPro)
		{
			try
			{
				$useCache             = !$this->item->getState('adminToolsForce', false, 'bool');
				$this->scans = $this->getModel()->adminToolsGetScans(
					$useCache,
					$this->item->getState('adminToolsFrom', 0, 'int'),
					$this->item->getState('adminToolsLimit', 20, 'int'),
				)?->items ?? [];
			}
			catch (\Exception $e)
			{
				$this->scans = $e;
			}
		}

		$document = $this->container->application->getDocument();

		$document->addScriptOptions(
			'panopticon.rememberTab', [
				'key' => 'panopticon.siteRead.' . $this->getModel()->id . '.rememberTab',
			]
		);
		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		$document->addScriptOptions('panopticon.siteRemember', [
			'extensionsFilters' => sprintf('panopticon.site%d.extensionFilters', $this->item->getId()),
			'collapsible' => sprintf('panopticon.site%d.collapsible', $this->item->getId())
		]);

		$this->addTooltipJavaScript();

		$document->addScriptOptions(
			'akeebabackup', [
				'enqueue' => $this->container->router->route(
					sprintf(
						'index.php?view=sites&task=akeebaBackupEnqueue&id=%d&%s=1',
						$this->item->id, $this->container->session->getCsrfToken()->getValue()
					)
				),
			]
		);

		return true;
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
		$originDescription = Text::_($originLanguageKey);

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
			$originDescription = Text::_('PANOPTICON_SITES_LBL_AKEEBABACKUP_ORIGIN_UNKNOWN');
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
	 * @throws  \Exception
	 * @since   1.0.0
	 */
	protected function getTimeInformation(object $record): array
	{
		$utcTimeZone = new DateTimeZone('UTC');

		try
		{
			$startTime = clone $this->container->dateFactory($record->backupstart, $utcTimeZone);
		}
		catch (\Exception $e)
		{
			$startTime = null;
		}

		try
		{
			$endTime = clone $this->container->dateFactory($record->backupend, $utcTimeZone);
		}
		catch (\Exception $e)
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
			is_null($startTime) ? '&nbsp;' : $startTime->format(Text::_('DATE_FORMAT_LC6'), true),
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
}