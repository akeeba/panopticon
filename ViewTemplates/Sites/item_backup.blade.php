<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupCannotConnectException;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupIsNotPro;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupNoInfoException;
use Akeeba\Panopticon\Model\Task;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$token               = $this->container->session->getCsrfToken()->getValue();
$lastRefreshResponse = $this->siteConfig->get('akeebabackup.lastRefreshResponse');

?>

@section('abReload')
    <a class="btn btn-outline-secondary btn-sm" role="button"
       href="@route(sprintf('index.php?view=site&task=read&id=%d&akeebaBackupForce=1', $this->item->getId()))"
       data-bs-toggle="tooltip" data-bs-placement="bottom"
       data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_REFRESH')"
    >
        <span class="fa fa-refresh" aria-hidden="true"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_REFRESH')</span>
    </a>
@stop

@section('abToggle')
    <button class="btn btn-success btn-sm ms-2" role="button"
            data-bs-toggle="collapse" href="#cardBackupBody"
            aria-expanded="true" aria-controls="cardBackupBody"
            data-bs-tooltip="tooltip" data-bs-placement="bottom"
            data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
    >
        <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
        <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
    </button>
@stop

@section('abErrorNoInfo')
    <div class="alert alert-info">
        @if($this->item->hasAkeebaBackup(false))
            @if(($lastRefreshResponse?->statusCode ?? null) != 200)
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_COMSERROR_HEAD')
                </p>
                <p class="text-danger">
                    <span class="fa fa-globe" aria-hidden="true"></span>
                    HTTP
                    <span class="badge bg-danger">
                        {{{ $lastRefreshResponse?->statusCode ?? '???' }}}
                    </span>
                    <span class="text-danger-emphasis">
                        {{{ $lastRefreshResponse?->reasonPhrase ?? ''  }}}
                    </span>
                </p>
					<?php
					$body = @json_decode($lastRefreshResponse?->body ?? '{}') ?>
                @if (is_array($body?->errors ?? '') && !empty($body?->errors ?? ''))
                    <ul class="text-body-secondary list-unstyled ms-4">
                        @foreach($body?->errors as $errorInfo)
                            <li class="font-monospace">
                                <span class="fa fa-terminal" aria-hidden="true"></span>
                                <span class="badge bg-secondary">
                                    {{{ $errorInfo?->code ?? '???' }}}
                                </span>
                                {{{ $errorInfo?->title ?? '' }}}
                            </li>
                        @endforeach
                    </ul>
                @endif
            @elseif(!$this->item->hasAkeebaBackup(true))
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_BODY')
                </p>
                <p>
                    <a href="https://www.akeeba.com/products/akeeba-backup.html"
                       target="_blank"
                       class="btn btn-info btn-sm">
                        <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITES_LBL_BOU_BTN_LEARN_MORE')
                    </a>
                </p>
            @else
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_BODY')
                </p>
            @endif
            @if ($this->canEdit && $this->item->hasAkeebaBackup(true))
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_SOLUTION_ADMIN')
                </p>
                <p>
                    <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $this->item->getId(), $token))"
                       role="button" class="btn btn-primary">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                    </a>
                </p>
            @elseif ($this->item->hasAkeebaBackup(true))
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_SOLUTION_NONADMIN')
                </p>
            @endif
        @else
            <h4 class="alert-heading fs-5">
                <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_HEAD')
            </h4>
            <p>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_BODY')
            </p>
        @endif
    </div>
@stop

@section('abErrorNotPro')
    <div class="alert alert-info">
        <h4 class="alert-heading fs-5">
            <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_HEAD')
        </h4>
        <p>
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_BODY')
        </p>
        @if ($this->canEdit)
            <p>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_SOLUTION')
            </p>
            <p>
                <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $this->item->getId(), $token))"
                   role="button" class="btn btn-primary">
                    <span class="fa fa-refresh" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                </a>
            </p>
        @endif
    </div>
@stop

@section('abErrorCannotConnect')
    <div class="alert alert-danger">
        <h4 class="alert-heading fs-5">
            <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_HEAD')
        </h4>
        <p>
            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_BODY')
        </p>
        <details class="text-info mb-3">
            <summary class="mb-1">
                <span class="fa fa-question-circle" aria-hidden="true"></span>
                <span>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIPS')</span>
            </summary>
            <ul class="text-body">
                @if($this->item->cmsType() === CMSType::JOOMLA)
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_1')</li>
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_2')</li>
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_3')</li>
                @else
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_1_WP')</li>
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_2_WP')</li>
                    <li>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_3_WP')</li>
                @endif
            </ul>
        </details>
        @if ($this->canEdit)
            <p>
                <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $this->item->getId(), $token))"
                   role="button" class="btn btn-primary">
                    <span class="fa fa-refresh" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                </a>
            </p>
        @endif
    </div>
@stop

@repeatable('abErrorException')
	<?php
	$token = $this->container->session->getCsrfToken()->getValue();
	?>
<div class="alert alert-danger">
    <h4 class="alert-heading fs-5">
        <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_HEAD')
    </h4>
    <p>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_BODY')
    </p>
    @if ($this->canEdit)
        <p>
            <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $this->item->getId(), $token))"
               role="button" class="btn btn-primary">
                <span class="fa fa-refresh" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
            </a>
        </p>
    @endif
    <p>
        <strong>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_TYPE')</strong>: {{ get_class($this->backupRecords) }}
    </p>
    <p>
        <strong>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_CODE')</strong>: {{ $this->backupRecords->getCode() }}
    </p>
    <p>
        <strong>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_MESSAGE')</strong>: {{ $this->backupRecords->getMessage() }}
    </p>
    @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
        <p>
            <strong>@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_TROUBLESHOOTING')</strong>:
        </p>
        <pre>{{ $this->backupRecords->getTraceAsString() }}</pre>
    @endif
</div>
@endrepeatable

@section('abRunStatus')
		<?php
		$allSchedules    = $this->item->akeebaBackupGetAllScheduledTasks();
		$allPending      = $allSchedules->filter(
			fn(Task $task) => in_array(
				$task->last_exit_code,
				[Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
			)
		)->count();
		$manualSchedules = $this->item->akeebaBackupGetEnqueuedTasks();
		$manualPending   = $manualSchedules->filter(
			fn(Task $task) => in_array(
				$task->last_exit_code,
				[Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
			)
		)->count();
		$manualDone      = $manualSchedules->filter(
			fn(Task $task) => $task->last_exit_code == Status::OK->value
		)->count();
		$manualError     = $manualSchedules->count() - $manualDone - $manualPending;
		?>
    @if($allPending)
        <div class="alert alert-info">
            <span class="fa fa-play" aria-hidden="true"></span>
            @plural('PANOPTICON_SITES_LBL_AKEEBABACKUP_EXECUTION_PENDING', $allPending)
        </div>
    @endif
    @if($manualError)
        <div class="alert alert-danger">
            <div class="d-flex flex-column flex-lg-row gap-0 gap-lg-2 align-items-center">
                <div class="flex-grow-1">
                    <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                    @plural('PANOPTICON_SITES_LBL_AKEEBABACKUP_MANUAL_ERROR', $manualError)
                </div>
                <div>
                    <a href="@route(sprintf('index.php?view=backuptasks&site_id=%d&manual=1', $this->item->getId()))"
                       role="button" class="btn btn-sm btn-outline-info "
                    >
                        <span class="fa fa-list" aria-hidden="true"></span>
                        @lang('PANOPTICON_BACKUPTASKS_LBL_VIEW_MANUAL')
                    </a>

                    {{--
                    <a href=""
                       role="button" class="btn btn-sm btn-outline-danger">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_BACKUPTASKS_LBL_RESET')
                    </a>
                    --}}
                </div>
            </div>
        </div>
    @endif
@stop

@section('abBackupControls')
    <div class="row row-cols-lg-auto g-4 align-items-center mb-3 p-2">
        {{-- Backup Schedule --}}
        <div class="col-12">
            <a href="@route(sprintf('index.php?view=backuptasks&site_id=%d&manual=0', $this->item->getId()))"
               role="button" class="btn btn-success">
                <span class="fa fa-calendar-alt me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_SCHEDULE')
            </a>
        </div>

        <div class="col-12 flex-grow-1">
            <label for="akeebaBackupTakeProfile" class="visually-hidden">
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_PROFILE')
            </label>
            <div class="input-group">
                {{ $this->container->html->select->genericList(
                    $this->getProfileOptions(),
                    'akeebaBackupTakeProfile',
                    [
                        'class' => 'form-select border-dark',
                    ],
                    selected: 1,
                    idTag: 'akeebaBackupTakeProfile'
                ) }}
                <button class="btn btn-primary" id="akeebaBackupTakeButton">
                    <span class="fa fa-play me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_STARTBACKUP')
                </button>
            </div>
        </div>

        <div class="col-12">
            <a href="@route(sprintf('index.php?view=site&task=read&id=%d&akeebaBackupForce=1', $this->item->getId()))"
               role="button" class="btn btn-secondary">
                <span class="fa fa-refresh" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_REFRESH')
            </a>
        </div>
    </div>
@stop

<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-hard-drive" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_BACKUP_HEAD') <small
                    class="text-muted">@lang('PANOPTICON_SITE_LBL_BACKUP_SUBHEAD')</small>
        </span>
        @yield('abReload')
        @yield('abToggle')
    </h3>
    <div class="card-body collapse show" id="cardBackupBody">
        @if ($this->backupRecords instanceof AkeebaBackupNoInfoException)
            @yield('abErrorNoInfo')
        @elseif ($this->backupRecords instanceof AkeebaBackupIsNotPro)
            @yield('abErrorNotPro')
        @elseif ($this->backupRecords instanceof AkeebaBackupCannotConnectException)
            @yield('abErrorCannotConnect')
        @elseif($this->backupRecords instanceof Throwable)
            @yieldRepeatable('abErrorException')
        @else
            @yield('abRunStatus')
            @yield('abBackupControls')

            <h4 class="border-bottom border-info-subtle pb-1 mt-2 mb-2">
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_LATEST')
            </h4>

            <table class="table">
                <thead class="table-dark">
                <tr>
                    <th scope="col" class="text-center d-none d-md-table-cell" style="max-width: 48px;">
                        <span aria-label="@lang('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')">
                            @lang('PANOPTICON_LBL_TABLE_HEAD_NUM')
                        </span>
                    </th>
                    <th scope="col" class="text-center" style="max-width: 3em">
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_FROZEN')
                    </th>
                    <th scope="col" class="text-center">
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DESCRIPTION')
                    </th>
                    <th scope="col" class="text-center" style="max-width: 3em;">
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_STATUS')
                    </th>
                    @if($this->canEdit)
                        <th scope="col" class="text-center d-none d-sm-table-cell">
                            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_ACTIONS')
                        </th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach ($this->backupRecords as $record)
						<?php
						[$originDescription, $originIcon] = $this->getOriginInformation($record);
						[$startTime, $duration, $timeZoneText] = $this->getTimeInformation($record);
						[$statusClass, $statusIcon] = $this->getStatusInformation($record);
						$profileName = $this->getProfileName($record);
						?>
                    <tr>
                        {{-- Backup ID --}}
                        <td class="d-none d-md-table-cell" valign="middle">
								<?= $record->id ?>
                        </td>

                        {{-- Frozen --}}
                        <td class="text-center" valign="middle">
                            @if ($record?->frozen ?? 0)
                                <span class="fa fa-snowflake text-info" aria-hidden="true"
                                      data-bs-tooltip="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_FROZEN')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_FROZEN')</span>
                            @else
                                <span class="fa fa-droplet text-body-tertiary" aria-hidden="true"
                                      data-bs-tooltip="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_UNFROZEN')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_UNFROZEN')</span>
                            @endif
                        </td>

                        {{-- Description, backup date, duration and size --}}
                        <td>
                            {{-- Row: origin and description --}}
                            <div class="d-flex flex-column flex-lg-row gap-1">
                                {{-- Origin --}}
                                <div>
                                <span class="{{ $originIcon }} fa-fw" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_ORIGIN'): {{{ $originDescription }}}"
                                ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_ORIGIN'): {{{ $originDescription }}}</span>
                                </div>
                                {{-- Description --}}
                                <div class="flex-grow-1">
                                    {{{ $record->description }}}
                                </div>
                                {{-- Comment show / hide --}}
                                @if (!empty($record->comment))
                                    <div>
                                        <button type="button" class="btn btn-sm btn-primary"
                                                data-bs-toggle="collapse" href="#akeebaBackupComment-{{ $record->id }}"
                                                aria-expanded="false"
                                                aria-controls="akeebaBackupComment-{{ $record->id }}"
                                                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                                                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
                                        >
                                            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
                                            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Row: Comment --}}
                            @if (!empty($record->comment))
                                <div class="collapse m-2 p-2 border rounded-2 bg-body-tertiary"
                                     id="akeebaBackupComment-{{ $record->id }}">
                                    {{ $record->comment }}
                                </div>
                            @endif

                            <div class="row mt-1">
                                {{-- Start Date --}}
                                <div class="col-lg-5">
                                    <span class="fa fa-calendar fa-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                                          data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_START')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_START')</span>
										<?= $startTime ?> <?= $timeZoneText ?>
                                </div>

                                {{-- Backup Duration --}}
                                <div class="col-lg">
                                    <span class="fa fa-stopwatch" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                                          data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DURATION')"
                                    ></span>&nbsp
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DURATION')</span>
										<?= $duration ?: '&mdash;' ?>
                                </div>

                                {{-- Backup size --}}
                                <div class="col-lg">
                                    <span class="fa fa-weight-hanging" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                                          data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_SIZE')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_SIZE')</span>
                                    @if ($record->meta == 'ok')
                                        {{ $this->formatFilesize($record->size) }}
                                    @elseif($record->total_size > 0)
                                        <i>{{ $this->formatFilesize($record->total_size) }}</i>
                                        @else
                                            &mdash;
                                    @endif
                                </div>
                            </div>

                            {{-- Backup Profile (condensed display) --}}
                            <div class="row mt-1">
                                <div class="col-md">
                                <span class="fa fa-users fa-fw" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                                      data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_PROFILE')"
                                ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_PROFILE')</span>
                                    #{{ (int) $record->profile_id }}.
                                    {{{ $profileName }}}
                                </div>
                            </div>
                        </td>

                        {{-- Status --}}
                        <td valign="middle" class="text-center">
                            <div class="badge rounded-pill fs-6 {{ $statusClass }}"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_STATUS_' . $record->meta)"
                            >
                                <div class="my-1">
                                    <span class="{{ $statusIcon }}" aria-hidden="true"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_STATUS_' . $record->meta)</span>
                                </div>
                            </div>
                        </td>

                        {{-- Actions --}}
                        @if($this->canEdit)
                            <td valign="middle" class="text-end">
                                @if (in_array(strtolower($record->meta), ['ok', 'complete']))
                                    <a href="@route(sprintf('index.php?view=sites&task=akeebaBackupDeleteFiles&id=%s&backup_id=%d&%s=1', $this->item->getId(), $record->id, $token))"
                                       role="button" class="btn btn-outline-danger"
                                       data-bs-toggle="tooltip" data-bs-placement="bottom"
                                       data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETEFILES')">
                                        <span class="fa fa-delete-left" aria-hidden="true"></span>
                                        <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETEFILES')</span>
                                    </a>
                                @endif

                                <a href="@route(sprintf('index.php?view=sites&task=akeebaBackupDelete&id=%s&backup_id=%d&%s=1', $this->item->getId(), $record->id, $token))"
                                   role="button" class="btn btn-danger"
                                   data-bs-toggle="tooltip" data-bs-placement="bottom"
                                   data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETE')">
                                    <span class="fa fa-trash-can" aria-hidden="true"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETE')</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @endforeach
                @if(empty($this->backupRecords))
                    <tr>
                        <td colspan="20">
                            <div class="alert alert-info m-2">
                                <span class="fa fa-info-circle" aria-hidden="true"></span>
                                @lang('PANOPTICON_SITE_LBL_AKEEBABACKUP_NO_RESULTS')
                            </div>
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>
        @endif
    </div>
</div>