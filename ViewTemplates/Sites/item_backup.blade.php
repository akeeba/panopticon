<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

/** @var \Akeeba\Panopticon\Model\Site $model */
$model               = $this->getModel();
$user                = $this->container->userManager->getUser();
$token               = $this->container->session->getCsrfToken()->getValue();
$config              = $model->getConfig();
$lastRefreshResponse = $config->get('akeebabackup.lastRefreshResponse');

?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-hard-drive" aria-hidden="true"></span>
        <span class="flex-grow-1">
        @lang('PANOPTICON_SITE_LBL_BACKUP_HEAD')
        </span>
        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardBackupBody"
                aria-expanded="{{ $shouldCollapse ? 'false' : 'true' }}" aria-controls="cardBackupBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body" id="cardBackupBody">
        @if ($this->backupRecords instanceof \Akeeba\Panopticon\Model\Exception\AkeebaBackupNoInfoException)
            <div class="alert alert-info">
                @if($model->hasAkeebaBackup())
                    <h4 class="alert-heading fs-5">
                        <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_HEAD')
                    </h4>
                    @if(($lastRefreshResponse?->statusCode ?? null) != 200)
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
                    @else
                        <p>
                            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_BODY')
                        </p>
                    @endif
                    @if ($user->authorise('panopticon.admin', $model))
                        <p>
                            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTLINKED_SOLUTION_ADMIN')
                        </p>
                        <p>
                            <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $model->getId(), $token))"
                               role="button" class="btn btn-primary">
                                <span class="fa fa-refresh" aria-hidden="true"></span>
                                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                            </a>
                        </p>
                    @else
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
        @elseif ($this->backupRecords instanceof \Akeeba\Panopticon\Model\Exception\AkeebaBackupIsNotPro)
            <div class="alert alert-info">
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CORE_BODY')
                </p>
                @if ($user->authorise('panopticon.admin', $model))
                    <p>
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_NOTFOUND_SOLUTION')
                    </p>
                    <p>
                        <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $model->getId(), $token))"
                           role="button" class="btn btn-primary">
                            <span class="fa fa-refresh" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                        </a>
                    </p>
                @endif
            </div>
        @elseif ($this->backupRecords instanceof \Akeeba\Panopticon\Model\Exception\AkeebaBackupCannotConnectException)
            <div class="alert alert-danger">
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_BODY')
                </p>
                @if ($user->authorise('panopticon.admin', $model))
                    <p>
                        <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $model->getId(), $token))"
                           role="button" class="btn btn-primary">
                            <span class="fa fa-refresh" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_RELINK')
                        </a>
                    </p>
                @endif
            </div>
        @elseif($this->backupRecords instanceof Throwable)
            <div class="alert alert-danger">
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_HEAD')
                </h4>
                <p>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_BODY')
                </p>
                @if ($user->authorise('panopticon.admin', $model))
                    <p>
                        <a href="@route(sprintf('index.php?view=site&task=akeebaBackupRelink&id=%d&%s=1', $model->getId(), $token))"
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
        @else
                <?php
                $allSchedules = $this->item->akeebaBackupGetAllScheduledTasks();
                $allPending = $allSchedules->filter(
                    fn(Task $task) => in_array(
                        $task->last_exit_code, [Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
                    )
                )->count();
                $manualSchedules = $this->item->akeebaBackupGetEnqueuedTasks();
                $manualPending   = $manualSchedules->filter(
                    fn(Task $task) => in_array(
                        $task->last_exit_code, [Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
                    )
                )->count();
                $manualDone   = $manualSchedules->filter(
                    fn(Task $task) => $task->last_exit_code == Status::OK->value
                )->count();
                $manualError  = $manualSchedules->count() - $manualDone - $manualPending;
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

                            <a href=""
                               role="button" class="btn btn-sm btn-outline-danger">
                                <span class="fa fa-refresh" aria-hidden="true"></span>
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row row-cols-lg-auto g-4 align-items-center mb-3 p-2">
                {{-- Backup Schedule --}}
                <div class="col-12">
                    <a href="@route(sprintf('index.php?view=backuptasks&site_id=%d&manual=0', $model->getId()))"
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
                        {{ \Awf\Html\Select::genericList(
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
                    <a href="@route(sprintf('index.php?view=site&task=read&id=%d&akeebaBackupForce=1', $model->getId()))"
                       role="button" class="btn btn-secondary">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_REFRESH')
                    </a>
                </div>
            </div>

            <h4 class="border-bottom border-info-subtle pb-1 mt-2 mb-2">
                @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_LATEST')
            </h4>

            <table class="table">
                <thead class="table-dark">
                <tr>
                    <th scope="col" class="text-center d-none d-md-table-cell" style="max-width: 48px;">
                        <span aria-hidden="true">@lang('PANOPTICON_LBL_TABLE_HEAD_NUM')</span>
                        <span class="visually-hidden">@lang('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')</span>
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
                    @if($user->authorise('panopticon.admin', $model))
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
                            <div class="d-flex flex-column flex-lg-row gap-2">
                                {{-- Origin --}}
                                <div>
                                <span class="{{ $originIcon }} me" aria-hidden="true"
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
                                    <span class="fa fa-calendar" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                                          data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_START')"
                                    ></span>&nbsp;
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
                                <span class="fa fa-users" aria-hidden="true"
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
                        @if($user->authorise('panopticon.admin', $model))
                            <td valign="middle" class="text-end">
                                @if (in_array(strtolower($record->meta), ['ok', 'complete']))
                                    <a href="@route(sprintf('index.php?view=sites&task=akeebaBackupDeleteFiles&id=%s&backup_id=%d&%s=1', $model->getId(), $record->id, $token))"
                                       role="button" class="btn btn-outline-danger"
                                       data-bs-toggle="tooltip" data-bs-placement="bottom"
                                       data-bs-title="@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETEFILES')">
                                        <span class="fa fa-delete-left" aria-hidden="true"></span>
                                        <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_DELETEFILES')</span>
                                    </a>
                                @endif

                                <a href="@route(sprintf('index.php?view=sites&task=akeebaBackupDelete&id=%s&backup_id=%d&%s=1', $model->getId(), $record->id, $token))"
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
                                @lang('PANOPTICON_MAIN_SITES_LBL_NO_RESULTS')
                            </div>
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>
        @endif
    </div>
</div>