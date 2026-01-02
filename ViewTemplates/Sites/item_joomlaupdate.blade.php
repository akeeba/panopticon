<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\SoftwareVersions\JoomlaVersion;
use Akeeba\Panopticon\Library\Enumerations\JoomlaUpdateRunState;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Registry\Registry;

$token                    = $this->container->session->getCsrfToken()->getValue();
$joomlaUpdateTask         = $this->item->getJoomlaUpdateTask();
$overridesChanged         = $this->siteConfig->get('core.overridesChanged');
$lastError                = trim($this->siteConfig->get('core.lastErrorMessage') ?? '');
$hasError                 = !empty($lastError);
$lastUpdateTimestamp      = $this->siteConfig->get('core.lastAttempt')
	? $this->timeAgo($this->siteConfig->get('core.lastAttempt'))
	:
	$this->getLanguage()->text('PANOPTICON_LBL_NEVER');
$jVersionHelper           = new JoomlaVersion($this->getContainer());
$showScheduleButton       = $this->joomlaUpdateRunState->isValidUpdateState();
$showCancelScheduleButton = false;
$currentVersion           = $this->siteConfig->get('core.current.version');
$latestVersion            = $this->siteConfig->get('core.latest.version');
$versionCurrent           = $currentVersion ? Version::create($currentVersion) : null;
$versionLatest            = $latestVersion ? Version::create($latestVersion) : null;
$versionFamilyInfo        = $currentVersion ? $jVersionHelper->getVersionInformation($currentVersion) : null;
$eolDate                  = $versionFamilyInfo?->dates?->eol ?? null;
$branchEolDate            = $versionFamilyInfo?->dates?->eolBranch ?? null;
$supportDate              = $versionFamilyInfo?->dates?->activeSupport ?? null;
$isSecurity               = $versionFamilyInfo?->security ?? null;
?>

@section('jUpdateLastErrorModal')
	<?php
	$siteInfoLastErrorModalID = 'silem-' . hash('md5', random_bytes(120)); ?>
    <div class="btn btn-danger btn-sm px-1 py-0" aria-hidden="true"
         data-bs-toggle="modal" data-bs-target="#{{ $siteInfoLastErrorModalID }}"
    >
        <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="bottom"
              data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO')"
              data-bs-content="{{{ $lastError }}}"></span>
    </div>

    <div class="modal fade" id="{{ $siteInfoLastErrorModalID }}"
         tabindex="-1" aria-labelledby="{{ $siteInfoLastErrorModalID }}_label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5"
                        id="{{ $siteInfoLastErrorModalID }}_label">
                        @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO')
                    </h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
                </div>
                <div class="modal-body">
                    <p class="text-break">
                        {{{ $lastError }}}
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        @lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
                    </button>
                </div>
            </div>
        </div>
    </div>

    <span class="visually-hidden">
        @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO') {{{ $lastError }}}
    </span>
@stop

@section('jUpdateStatus')
    @if (empty($currentVersion))
        <div class="alert alert-danger">
            <h4 class="alert alert-heading h5 p-0">
                <span class="fab fa-joomla d-none d-md-inline" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_JUPDATE_NO_VERSION_HEAD')
            </h4>
            <p>
                @lang('PANOPTICON_SITE_LBL_JUPDATE_NO_VERSION_BODY')
            </p>
        </div>
    @elseif(!$this->siteConfig->get('core.extensionAvailable', true))
        <div class="alert alert-danger">
            <h4 class="alert-heading h6">
                <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
            </h4>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_ERR_JOOMLA_FILES_EXT_MISSING')
            @lang('PANOPTICON_SITE_LBL_JUPDATE_SEEK_HELP_JFORUM')
        </div>
    @elseif(!$this->siteConfig->get('core.updateSiteAvailable', true))
        <div class="alert alert-danger">
            <h4 class="alert-heading h6">
                <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
            </h4>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_ERR_UPDATESITE_MISSING')
            @if(version_compare($currentVersion, '4.0.0', 'ge'))
                @lang('PANOPTICON_SITE_LBL_JUPDATE_UPDATESITE_FIX_J4')
            @elseif(version_compare($currentVersion, '3.6.0', 'ge'))
                @lang('PANOPTICON_SITE_LBL_JUPDATE_UPDATESITE_FIX_J3')
            @else
                @lang('PANOPTICON_SITE_LBL_JUPDATE_SEEK_HELP_JFORUM')
            @endif
            @lang('PANOPTICON_SITE_LBL_JUPDATE_ALT_FIX')
            <div class="mt-3 mb-1 px-3">
                <a class="btn btn-success w-100" role="button"
                   href="@route(sprintf('index.php?view=site&task=fixJoomlaCoreUpdateSite&id=%d&%s=1', $this->item->id, $token))">
                    <span class="fa fa-wrench" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_COREUPDATESITEFIX_BTN')
                </a>
            </div>
        </div>
    @elseif ($this->siteConfig->get('core.canUpgrade', false))
        <div class="alert alert-warning">
            <h4 class="alert alert-heading h5 p-0">
                <span class="fab fa-joomla d-none d-md-inline" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_AVAILABLE_UPDATE', $this->escape($latestVersion))
            </h4>
            <p class="mb-1">
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_CURRENT_VERSION', $this->escape($currentVersion))
            </p>
            {{-- Is this a major, minor, or patch update? --}}
            @if ($versionCurrent?->versionFamily() === $versionLatest?->versionFamily())
                <p class="text-success-emphasis my-1">
                    <span class="fa fa-check-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_IS_PATCH_RELEASE')
                </p>
            @elseif ($versionCurrent?->major() === $versionLatest?->major())
                <p class="text-warning-emphasis fw-medium my-1">
                    <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_IS_MINOR_RELEASE')
                </p>
                <p class="text-warning-emphasis my-1">
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_MINOR_RELEASE_ADMONISHMENT')
                </p>
            @else
                <p class="text-danger-emphasis fw-bold my-1">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_IS_MAJOR_RELEASE')
                </p>
                <p class="text-danger-emphasis my-1">
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_MAJOR_RELEASE_ADMONISHMENT')
                </p>
            @endif
        </div>
    @elseif($jVersionHelper->isEOLMajor($currentVersion))
        <div class="alert alert-danger text-danger-emphasis">
            <h4 class="alert alert-heading h5 p-0 m-0">
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_EOL', $this->escape($latestVersion))
            </h4>
            <p class="my-1 fw-medium">
                <span class="fa fa-fw fa-skull" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_EOL_MAJOR', $versionFamilyInfo?->series)
            </p>
            {{-- Is there a new version available, which cannot be installed? --}}
            @if (version_compare($latestVersion, $currentVersion, 'lt'))
                <hr>
                <p class="my-2 text-warning-emphasis fw-semibold">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_CANNOT_INSTALL', $this->escape($latestVersion))
                </p>
                <p class="text-muted">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_FIX_TO_INSTALL_NEXT_VERSION', $this->escape($this->siteConfig->get('core.php')))
                </p>
            @endif
        </div>
        <p class="text-danger-emphasis">
            <span class="fa fa-fw fa-warning" aria-hidden="true"></span>
            @sprintf(
                'PANOPTICON_SITE_LBL_JUPDATE_EOL_DATE',
                $versionFamilyInfo?->series,
                $this->formatDate($eolDate)
            )
        </p>
    @elseif($jVersionHelper->isEOLBranch($currentVersion))
        <div class="alert alert-danger text-danger-emphasis">
            <h4 class="alert alert-heading h5 p-0 m-0">
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_EOL', $this->escape($latestVersion))
            </h4>
            <p class="my-1 fw-medium">
                <span class="fa fa-fw fa-book-dead" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_EOL_MINOR', $versionCurrent?->versionFamily())
            </p>
            {{-- Is there a new version available, which cannot be installed? --}}
            @if (version_compare($latestVersion, $currentVersion, 'lt'))
                <hr>
                <p class="my-2 text-warning-emphasis fw-semibold">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_CANNOT_INSTALL', $this->escape($latestVersion))
                </p>
                <p class="text-muted">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_FIX_TO_INSTALL_NEXT_VERSION', $this->escape($this->siteConfig->get('core.php')))
                </p>
            @endif
        </div>
    @else
        <div class="alert alert-success">
            <h4 class="alert alert-heading h5 p-0 m-0">
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_UP_TO_DATE', $this->escape($currentVersion))
            </h4>
            {{-- Is there a new version available, which cannot be installed? --}}
            @if (version_compare($latestVersion, $currentVersion, 'lt'))
                <hr>
                <p class="my-2 text-warning-emphasis fw-semibold">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_CANNOT_INSTALL', $this->escape($latestVersion))
                </p>
                <p class="text-muted">
                    @sprintf('PANOPTICON_SITE_LBL_JUPDATE_FIX_TO_INSTALL_NEXT_VERSION', $this->escape($this->siteConfig->get('core.php')))
                </p>
            @endif
        </div>
        @if($isSecurity)
            <p class="my-1 fw-medium text-warning">
                <span class="fa fa-fw fa-warning" aria-hidden="true"></span>
                @sprintf(
                    'PANOPTICON_SITE_LBL_JUPDATE_SECURITY_DATE',
                    $versionFamilyInfo?->series,
                    $this->formatDate($supportDate),
                    $this->formatDate($eolDate)
                )
            </p>
        @elseif ($branchEolDate == $eolDate)
            <p class="my-1 text-muted">
                <span class="fa fa-fw fa-check-circle" aria-hidden="true"></span>
                @sprintf(
                    'PANOPTICON_SITE_LBL_JUPDATE_LAST_STABLE_DATE',
                   $versionFamilyInfo?->series,
                    $this->formatDate($supportDate),
                    $this->formatDate($eolDate)
                )
            </p>
        @else
            <p class="my-1 text-muted">
                <span class="fa fa-fw fa-check-circle" aria-hidden="true"></span>
                @sprintf(
                    'PANOPTICON_SITE_LBL_JUPDATE_STABLE_DATE',
                   $versionCurrent?->versionFamily(),
                    $this->formatDate($supportDate),
                    $this->formatDate($branchEolDate)
                )
            </p>
        @endif
    @endif
@stop

@section('jUpdateSchedule')
    @if ($this->joomlaUpdateRunState === JoomlaUpdateRunState::SCHEDULED || $this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_SCHEDULED)
			<?php
			$showScheduleButton       = false;
			$showCancelScheduleButton = true;
			?>
        <p>
            @if ($joomlaUpdateTask?->next_execution)
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULED', $this->formatDate($joomlaUpdateTask->next_execution, 'DATE_FORMAT_LC7'))
            @else
                @lang('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULED_ASAP')
            @endif
        </p>
    @elseif ($this->joomlaUpdateRunState === JoomlaUpdateRunState::RUNNING || $this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_RUNNING)
			<?php
			$showScheduleButton = false; ?>
        <p>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_RUNNING')
        </p>
    @elseif ($this->joomlaUpdateRunState === JoomlaUpdateRunState::ERROR || $this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_ERROR)
        {{-- Task error condition --}}
			<?php
			$status = Status::tryFrom($joomlaUpdateTask->last_exit_code) ?? Status::NO_ROUTINE;
			?>
        <p class="text-warning-emphasis">
            @lang('PANOPTICON_SITE_LBL_JUPDATE_ERRORED')
            {{ $status->forHumans() }}
        </p>
        @if ($status->value === Status::EXCEPTION->value)
				<?php
				$storage = ($joomlaUpdateTask->storage instanceof Registry) ? $joomlaUpdateTask->storage
					: (new Registry($joomlaUpdateTask->storage));
				?>
            <p>
                @lang('PANOPTICON_SITE_LBL_JUPDATE_THE_ERROR_REPORTED_WAS')
            </p>
            <p class="text-dark">
                {{{ $storage->get('error') }}}
            </p>
            @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
                <p>@lang('PANOPTICON_SITE_LBL_JUPDATE_ERROR_TRACE')</p>
                <pre>{{{ $storage->get('trace') }}}</pre>
            @endif
        @endif

        {{-- Button to reset the error (by removing the failed task) --}}
        <a href="@route(sprintf('index.php?view=site&task=clearUpdateScheduleError&id=%d&%s=1', $this->item->id, $token))"
           class="btn btn-primary" role="button">
            <span class="fa fa-eraser" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_CLEAR_ERROR')
        </a>
    @elseif($this->joomlaUpdateRunState != JoomlaUpdateRunState::CANNOT_UPGRADE)
        <p>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_NOT_SCHEDULED')
        </p>
    @endif

    @if ($showScheduleButton)
        <a href="@route(sprintf('index.php?view=site&task=scheduleJoomlaUpdate&id=%d&%s=1', $this->item->id, $token))"
           class="btn btn-outline-warning" role="button">
            <span class="fa fa-clock" aria-hidden="true"></span>
            @sprintf('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_UPDATE', $this->escape($latestVersion))
        </a>
    @elseif($showCancelScheduleButton)
        <a href="@route(sprintf('index.php?view=site&task=unscheduleJoomlaUpdate&id=%d&%s=1', $this->item->id, $token))"
           class="btn btn-outline-danger" role="button">
            <span class="fa fa-cancel" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_UNSCHEDULE_UPDATE')
        </a>
    @endif
@stop

@section('jUpdateOverrides')
    @if ($overridesChanged > 0)
        <div class="alert alert-warning mt-4">
            <h4 class="alert-heading fs-5">
                <span class="fa fa-arrows-to-eye fa-fw" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)
            </h4>
            <p class="mt-2 mb-0 py-1">
                <a href="@route(sprintf('index.php?view=overrides&site_id=%d', $this->item->id))"
                   class="btn btn-primary" role="button"
                >
                    <span class="fa fa-arrows-to-eye fa-fw" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_BTN_TEMPLATE_OVERRIDES_CHECK')
                </a>
            </p>
        </div>
    @endif
@stop

@section('jUpdateRefreshCore')
    @if ($this->item->canRefreshCoreJoomlaFiles())
        <hr class="mt-4">

        <details>
            <summary class="text-info">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_CORE_PROMPT')
            </summary>
            <div class="mt-2 pt-3">
                @if (!$this->joomlaUpdateRunState->isValidRefreshState())
                    <p>
                        <a href="@route(sprintf('index.php?view=site&task=scheduleJoomlaUpdate&id=%d&force=1&%s=1', $this->item->id, $token))"
                           class="btn btn-outline-secondary" role="button">
                            <span class="fa fa-clock" aria-hidden="true"></span>
                            @sprintf('PANOPTICON_SITE_LBL_JUPDATE_BTN_REFRESH_CORE_PROMPT', $this->escape($latestVersion))
                        </a>
                    </p>
                @endif
                <p class="small text-muted">
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_CORE_NOTE')
                </p>
            </div>
        </details>

        @if($this->joomlaUpdateRunState->isValidRefreshState())
            @if ($this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_SCHEDULED)
                <div class="alert alert-info mt-2">
                    @if ($joomlaUpdateTask?->next_execution)
                        @sprintf('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_SCHEDULED', $this->formatDate($joomlaUpdateTask->next_execution, 'DATE_FORMAT_LC7'))
                    @else
                        @lang('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_SCHEDULED_ASAP')
                    @endif
                </div>
            @elseif($this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_RUNNING)
                <div class="alert alert-info mt-2">
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_RUNNING')
                </div>
            @elseif ($this->joomlaUpdateRunState === JoomlaUpdateRunState::REFRESH_ERROR)
					<?php
					$status  = Status::tryFrom($joomlaUpdateTask->last_exit_code) ?? Status::NO_ROUTINE;
					$storage = ($joomlaUpdateTask->storage instanceof Registry)
						? $joomlaUpdateTask->storage
						: (new Registry($joomlaUpdateTask->storage));
					?>
                <div class="alert alert-danger mt-2">
                    <h4 class="alert-heading h6">
                        @lang('PANOPTICON_SITE_LBL_JUPDATE_REFRESH_ERRORED')
                        <span class="fw-normal fst-italic">
                            {{ $status->forHumans() }}
                            </span>
                    </h4>

                    @if ($status->value === Status::EXCEPTION->value)
                        <p>
                            @lang('PANOPTICON_SITE_LBL_JUPDATE_THE_ERROR_REPORTED_WAS')
                        </p>
                        <p class="text-dark">
                            {{{ $storage->get('error') }}}
                        </p>
                        @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
                            <p>@lang('PANOPTICON_SITE_LBL_JUPDATE_ERROR_TRACE')</p>
                            <pre>{{{ $storage->get('trace') }}}</pre>
                        @endif
                    @endif

                    {{-- Button to reset the error (by removing the failed task) --}}
                    <a href="@route(sprintf('index.php?view=site&task=clearUpdateScheduleError&id=%d&%s=1', $this->item->id, $token))"
                       class="btn btn-primary mt-3" role="button">
                        <span class="fa fa-eraser" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_CLEAR_ERROR')
                    </a>
                </div>
            @endif
        @endif
    @endif
@stop

<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fab fa-joomla d-none d-md-inline" aria-hidden="true"></span>
        <span class="flex-grow-1">@lang('PANOPTICON_SITE_LBL_JUPDATE_HEAD')</span>
        <a class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&%s=1', $this->item->id, $token))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_BTN_JUPDATE_RELOAD')"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_SITE_BTN_JUPDATE_RELOAD')
            </span>
        </a>
    </h3>
    <div class="card-body">
        {{-- Last Error and Last Check --}}
        <div class="small mb-3">
            @if ($lastError)
                @yield('jUpdateLastErrorModal')
            @endif
            <span class="{{ $hasError ? 'text-danger' : 'text-body-tertiary' }}">
                <strong>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_LAST_CHECKED')
                </strong>
                {{ $lastUpdateTimestamp }}
            </span>
        </div>

        {{-- Joomla! Update status --}}
        @yield('jUpdateStatus')

        {{-- Scheduling status and controls --}}
        @yield('jUpdateSchedule')

        {{-- Notice about changed template overrides --}}
        @yield('jUpdateOverrides')

        {{-- Refresh core files --}}
        @yield('jUpdateRefreshCore')

        {{-- Where did I get my data? --}}
        <details class="mt-3 mb-1 small text-secondary">
            <summary>@lang('PANOPTICON_SITE_LBL_WHERE_DID_I_GET_DATA')</summary>
            <p class="mt-1 mb-0">
                @lang('PANOPTICON_SITE_LBL_JUPDATE_SOURCE_INFO')
            </p>
        </details>
    </div>
</div>
