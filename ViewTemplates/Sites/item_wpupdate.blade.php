<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Enumerations\WordPressUpdateRunState;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Registry\Registry;

$token                    = $this->container->session->getCsrfToken()->getValue();
$wpUpdateTask             = $this->item->getWordPressUpdateTask();
$lastError                = trim($this->siteConfig->get('core.lastErrorMessage') ?? '');
$hasError                 = !empty($lastError);
$lastUpdateTimestamp      = $this->siteConfig->get('core.lastAttempt')
	? $this->timeAgo($this->siteConfig->get('core.lastAttempt'))
	:
	$this->getLanguage()->text('PANOPTICON_LBL_NEVER');
$wpVersionHelper          = new \Akeeba\Panopticon\Library\SoftwareVersions\WordPressVersion($this->getContainer());
$showScheduleButton       = $this->wpUpdateRunState->isValidUpdateState();
$showCancelScheduleButton = false;
$currentVersion           = $this->siteConfig->get('core.current.version');
$latestVersion            = $this->siteConfig->get('core.latest.version');
$versionCurrent           = $currentVersion ? Version::create($currentVersion) : null;
$versionLatest            = $latestVersion ? Version::create($latestVersion) : null;
$versionFamilyInfo        = $currentVersion ? $wpVersionHelper->getVersionInformation($currentVersion) : null;
$eolDate                  = $versionFamilyInfo?->dates?->eol ?? null;
?>

@section('wpUpdateLastErrorModal')
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

@section('wpUpdateStatus')
    @if (empty($currentVersion))
        <div class="alert alert-danger">
            <h4 class="alert alert-heading h5 p-0">
                <span class="fab fa-wordpress d-none d-md-inline" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_WPUPDATE_NO_VERSION_HEAD')
            </h4>
            <p>
                @lang('PANOPTICON_SITE_LBL_WPUPDATE_NO_VERSION_BODY')
            </p>
        </div>
    @elseif ($this->siteConfig->get('core.canUpgrade', false))
        <div class="alert alert-warning">
            <h4 class="alert alert-heading h5 p-0">
                <span class="fab fa-wordpress d-none d-md-inline" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_AVAILABLE_UPDATE', $this->escape($latestVersion))
            </h4>
            <p class="mb-1">
                @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_CURRENT_VERSION', $this->escape($currentVersion))
            </p>
            {{-- Is this a major, minor, or patch update? --}}
            @if ($versionCurrent?->versionFamily() === $versionLatest?->versionFamily())
                <p class="text-success-emphasis my-1">
                    <span class="fa fa-check-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_WPUPDATE_IS_PATCH_RELEASE')
                </p>
            @elseif ($versionCurrent?->major() === $versionLatest?->major())
                <p class="text-warning-emphasis fw-medium my-1">
                    <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_WPUPDATE_IS_MINOR_RELEASE')
                </p>
                <p class="text-warning-emphasis my-1">
                    @lang('PANOPTICON_SITE_LBL_WPUPDATE_MINOR_RELEASE_ADMONISHMENT')
                </p>
            @else
                <p class="text-danger-emphasis fw-bold my-1">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_WPUPDATE_IS_MAJOR_RELEASE')
                </p>
                <p class="text-danger-emphasis my-1">
                    @lang('PANOPTICON_SITE_LBL_WPUPDATE_MAJOR_RELEASE_ADMONISHMENT')
                </p>
            @endif
        </div>
    @elseif($wpVersionHelper->isEOL($currentVersion))
        <div class="alert alert-danger text-danger-emphasis">
            <h4 class="alert alert-heading h5 p-0 m-0">
                @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_EOL', $this->escape($latestVersion))
            </h4>
            {{-- Is there a new version available, which cannot be installed? --}}
            @if (version_compare($latestVersion, $currentVersion, 'lt'))
                <hr>
                <p class="my-2 text-warning-emphasis fw-semibold">
                    @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_CANNOT_INSTALL', $this->escape($latestVersion))
                </p>
            @endif
        </div>
        <p class="text-danger-emphasis">
            <span class="fa fa-fw fa-warning" aria-hidden="true"></span>
            @sprintf(
                'PANOPTICON_SITE_LBL_WPUPDATE_EOL_DATE',
                $versionFamilyInfo?->series,
                $this->formatDate($eolDate)
            )
        </p>
    @else
        <div class="alert alert-success">
            <h4 class="alert alert-heading h5 p-0 m-0">
                @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_UP_TO_DATE', $this->escape($currentVersion))
            </h4>
            {{-- Is there a new version available, which cannot be installed? --}}
            @if (version_compare($latestVersion, $currentVersion, 'lt'))
                <hr>
                <p class="my-2 text-warning-emphasis fw-semibold">
                    @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_CANNOT_INSTALL', $this->escape($latestVersion))
                </p>
            @endif
        </div>
    @endif
@stop

@section('wpUpdateSchedule')
    @if ($this->wpUpdateRunState === WordPressUpdateRunState::SCHEDULED)
			<?php
			$showScheduleButton       = false;
			$showCancelScheduleButton = true;
			?>
        <p>
            @if ($wpUpdateTask?->next_execution)
                @sprintf('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULED', $this->formatDate($wpUpdateTask->next_execution, 'DATE_FORMAT_LC7'))
            @else
                @lang('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULED_ASAP')
            @endif
        </p>
    @elseif ($this->wpUpdateRunState === WordPressUpdateRunState::RUNNING)
			<?php
			$showScheduleButton = false; ?>
        <p>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_RUNNING')
        </p>
    @elseif ($this->wpUpdateRunState === WordPressUpdateRunState::ERROR)
        {{-- Task error condition --}}
			<?php
			$status = Status::tryFrom($wpUpdateTask->last_exit_code) ?? Status::NO_ROUTINE;
			?>
        <p class="text-warning-emphasis">
            @lang('PANOPTICON_SITE_LBL_JUPDATE_ERRORED')
            {{ $status->forHumans() }}
        </p>
        @if ($status->value === Status::EXCEPTION->value)
				<?php
				$storage = ($wpUpdateTask->storage instanceof Registry) ? $wpUpdateTask->storage
					: (new Registry($wpUpdateTask->storage));
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
    @elseif($this->wpUpdateRunState != WordPressUpdateRunState::CANNOT_UPGRADE)
        <p>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_NOT_SCHEDULED')
        </p>
    @endif

    @if ($showScheduleButton)
        <a href="@route(sprintf('index.php?view=site&task=scheduleWordPressUpdate&id=%d&%s=1', $this->item->id, $token))"
           class="btn btn-outline-warning" role="button">
            <span class="fa fa-clock" aria-hidden="true"></span>
            @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_SCHEDULE_UPDATE', $this->escape($latestVersion))
        </a>
    @elseif($showCancelScheduleButton)
        <a href="@route(sprintf('index.php?view=site&task=unscheduleWordPressUpdate&id=%d&%s=1', $this->item->id, $token))"
           class="btn btn-outline-danger" role="button">
            <span class="fa fa-cancel" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_JUPDATE_UNSCHEDULE_UPDATE')
        </a>
    @endif
@stop

<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fab fa-wordpress d-none d-md-inline" aria-hidden="true"></span>
        <span class="flex-grow-1">@lang('PANOPTICON_SITE_LBL_WPUPDATE_HEAD')</span>
        <a class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&%s=1', $this->item->id, $token))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_BTN_WPUPDATE_RELOAD')"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_SITE_BTN_WPUPDATE_RELOAD')
            </span>
        </a>
    </h3>
    <div class="card-body">
        {{-- Last Error and Last Check --}}
        <div class="small mb-3">
            @if ($lastError)
                @yield('wpUpdateLastErrorModal')
            @endif
            <span class="{{ $hasError ? 'text-danger' : 'text-body-tertiary' }}">
                <strong>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_LAST_CHECKED')
                </strong>
                {{ $lastUpdateTimestamp }}
            </span>
        </div>

        {{-- Joomla! Update status --}}
        @yield('wpUpdateStatus')

        {{-- Scheduling status and controls --}}
        @yield('wpUpdateSchedule')

        {{-- Where did I get my data? --}}
        <details class="mt-3 mb-1 small text-secondary">
            <summary>@lang('PANOPTICON_SITE_LBL_WHERE_DID_I_GET_DATA')</summary>
            <p class="mt-1 mb-0">
                @sprintf('PANOPTICON_SITE_LBL_WPUPDATE_SOURCE_INFO', 'https://endoflife.date/wordpress')
            </p>
        </details>
    </div>
</div>
