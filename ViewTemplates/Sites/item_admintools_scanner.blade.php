<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$token = $this->container->session->getCsrfToken()->getValue();

?>

@repeatable('atScansError')
    <div class="alert alert-danger">
        <h4 class="alert-heading fs-5">
            <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_SCANNER_ERROR_HEAD')
        </h4>
        <p>
            @lang('PANOPTICON_SITE_LBL_SCANNER_ERROR_BODY')
        </p>
        <p class="fs-5 fw-medium mb-1">
            <span class="badge bg-danger">{{ $this->scans->getCode() }}</span> {{ get_class($this->scans) }}
        </p>
        <p class="mx-2 px-2 border-4 border-danger border-start">
            {{ $this->scans->getMessage() }}
        </p>
        @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
            <details>
                <summary>
                    @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_APIERROR_TROUBLESHOOTING')
                </summary>

                <pre>{{ $this->scans->getTraceAsString() }}</pre>
            </details>
        @endif
    </div>
@endrepeatable

@section('atScansScheduleInfo')
    <?php
    $allSchedules    = $this->item->adminToolsGetAllScheduledTasks();
    $allPending      = $allSchedules->filter(
        fn(Task $task) => in_array(
            $task->last_exit_code, [Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
        )
    )->count();
    $manualSchedules = $this->item->adminToolsGetEnqueuedTasks();
    $manualPending   = $manualSchedules->filter(
        fn(Task $task) => in_array(
            $task->last_exit_code, [Status::RUNNING->value, Status::WILL_RESUME->value, Status::INITIAL_SCHEDULE->value]
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
            @plural('PANOPTICON_SITES_LBL_ADMINTOOLS_EXECUTION_PENDING', $allPending)
        </div>
    @endif

    @if($manualError)
        <div class="alert alert-danger">
            <div class="d-flex flex-column flex-lg-row gap-0 gap-lg-2 align-items-center">
                <div class="flex-grow-1">
                    <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                    @plural('PANOPTICON_SITES_LBL_ADMINTOOLS_MANUAL_ERROR', $manualError)
                </div>
                <div>
                    <a href="@route(sprintf('index.php?view=scannertasks&site_id=%d&manual=1', $this->item->getId()))"
                       role="button" class="btn btn-sm btn-outline-info "
                    >
                        <span class="fa fa-list" aria-hidden="true"></span>
                        @lang('PANOPTICON_SCANNERTASKS_LBL_VIEW_MANUAL')
                    </a>

                    <a href=""
                       role="button" class="btn btn-sm btn-outline-danger">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SCANNERTASKS_LBL_RESET')
                    </a>
                </div>
            </div>
        </div>
    @endif
@stop

@section('atScansScheduleControls')
    <div class="row row-cols-lg-auto g-4 align-items-center mb-3 p-2">
    {{-- PHP File Change Scanner Schedule --}}
    <div class="col-12">
        <a href="@route(sprintf('index.php?view=scannertasks&site_id=%d&manual=0', $this->item->getId()))"
           role="button" class="btn btn-success">
            <span class="fa fa-calendar-alt me-1" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_ADMINTOOLS_SCHEDULE')
        </a>
    </div>

    <div class="col-12 flex-grow-1">
        <a href="@route(sprintf('index.php?view=sites&task=adminToolsEnqueue&id=%d&%s=1', $this->item->id,$this->container->session->getCsrfToken()->getValue()))"
           class="btn btn-primary" role="button" id="adminToolsScanButton">
            <span class="fa fa-play me-1" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_ADMINTOOLS_STARTSCAN')
        </a>
    </div>

    <div class="col-12">
        <a href="@route(sprintf('index.php?view=site&task=read&id=%d&adminToolsForce=1', $this->item->getId()))"
           role="button" class="btn btn-secondary">
            <span class="fa fa-refresh" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_ADMINTOOLS_REFRESH')
        </a>
    </div>
</div>
@stop

<h4 class="border-bottom border-info-subtle pb-1 mt-4 mb-2">
    @lang('PANOPTICON_SITE_LBL_SCANNER_HEAD')
</h4>

@if ($this->scans instanceof Throwable)
    @yieldRepeatable('atScansError')
@else
    @yield('atScansScheduleInfo')
    @yield('atScansScheduleControls')

    <p class="small text-info mb-3">
        @lang('PANOPTICON_SITE_LBL_SCANNER_TIP')
    </p>

    <table class="table">
        <thead class="table-dark">
        <tr>
            <th rowspan="2" class="d-none d-md-table-cell">
                @lang('PANOPTICON_LBL_TABLE_HEAD_NUM')
            </th>
            <th rowspan="2">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_STATUS')
            </th>
            <th rowspan="2">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_DATE_TIME')
            </th>
            <th colspan="4" class="text-center">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_FILES')
            </th>
            <th rowspan="2" class="d-none d-md-table-cell">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_ACTIONS')
            </th>
        </tr>
        <tr>
            <th>
            <span class="fa fa-file-alt d-md-none" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="top"
                  data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_TOTAL')"
            ></span>
                <span class="d-none d-md-inline">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_TOTAL')
            </span>
            </th>
            <th>
            <span class="fa fa-file-circle-plus d-md-none" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="top"
                  data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NEW')"
            ></span>
                <span class="d-none d-md-inline">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NEW')
            </span>
            </th>
            <th>
            <span class="fa fa-file-edit d-md-none" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="top"
                  data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_MODIFIED')"
            ></span>
                <span class="d-none d-md-inline">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_MODIFIED')
            </span>
            </th>
            <th>
            <span class="fa fa-file-circle-exclamation d-md-none" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="top"
                  data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SUSPICIOUS')"
            ></span>
                <span class="d-none d-md-inline">
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SUSPICIOUS')
            </span>
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->scans as $scan)
            <?php
            $backendUri = new Uri($this->item->getAdminUrl());
            $backendUri->setVar('option', 'com_admintools');
            $backendUri->setVar('view', 'Scanalerts');
            $backendUri->setVar('scan_id', (int) $scan->id);
            ?>
            <tr>
                <td class="d-none d-md-table-cell">
                    <a href="{{{ $backendUri->toString() }}}"
                       target="_blank">
                        {{ (int) $scan->id }}
                    </a>
                </td>
                <td>
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <div>
                            <div class="badge bg-secondary">
                                @if ($scan->origin === 'backend')
                                    <span class="fa fa-desktop fw-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_BACKEND')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_BACKEND')</span>
                                @elseif ($scan->origin === 'cli')
                                    <span class="fa fa-terminal fw-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_CLI')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_CLI')</span>
                                @elseif ($scan->origin === 'joomla')
                                    <span class="fab fa-joomla fw-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_JOOMLA')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_JOOMLA')</span>
                                @elseif ($scan->origin === 'api')
                                    <span class="fa fa-code fw-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_PANOPTICON')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_PANOPTICON')</span>
                                @else
                                    <span class="fa fa-question fw-fw" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_UNKNOWN')"
                                    ></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_UNKNOWN')</span>
                                @endif
                            </div>
                        </div>
                        <div class="{{ $scan->status === 'complete' ? 'text-success' : ($scan->status === 'run' ? 'text-warning' : 'text-danger') }}">
                            {{-- complete fail run --}}
                            @if ($scan->status === 'complete')
                                <span class="fa fa-check-circle fw-fw d-md-none" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_COMPLETE')"
                                ></span>
                                <span class="d-none d-md-inline">
                            <span class="fa fa-check-circle fw-fw" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_COMPLETE')
                        </span>
                                <span class="d-md-none visually-hidden">
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_COMPLETE')
                        </span>
                            @elseif ($scan->status === 'run')
                                <span class="fa fa-play-circle fw-fw d-md-none" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_RUNNING')"
                                ></span>
                                <span class="d-none d-md-inline">
                            <span class="fa fa-play-circle fw-fw" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_RUNNING')
                        </span>
                                <span class="d-md-none visually-hidden">
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_RUNNING')
                        </span>
                            @else
                                <span class="fa fa-exclamation-circle fw-fw d-md-none" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_FAILED')"
                                ></span>
                                <span class="d-none d-md-inline">
                            <span class="fa fa-exclamation-circle fw-fw" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_FAILED')
                        </span>
                                <span class="d-md-none visually-hidden">
                            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_FAILED')
                        </span>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                <span class="fw-semibold d-md-none" aria-hidden="true">
                    @if (!empty($scan->scanstart) && $scan->scanstart != '0000-00-00 00:00:00')
                        {{ $this->getContainer()->html->basic->date($scan->scanstart, $this->getLanguage()->text('DATE_FORMAT_LC6')) }}
                    @endif
                </span>
                        <span class="fw-semibold d-none d-md-block">
                    @if (!empty($scan->scanstart) && $scan->scanstart != '0000-00-00 00:00:00')
                                {{ $this->getContainer()->html->basic->date($scan->scanstart, $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                            @endif
                </span>
                        <span class="text-muted d-none d-md-block">
                    @if (!empty($scan->scanend) && $scan->scanend != '0000-00-00 00:00:00')
                                {{ $this->getContainer()->html->basic->date($scan->scanend, $this->getLanguage()->text('DATE_FORMAT_LC7')) }}
                            @endif
                </span>
                        <div class="d-md-none">
                            <a href="{{{ $backendUri->toString() }}}"
                               class="btn btn-outline-success btn-sm"
                               target="_blank">
                                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_VIEW_REPORT')
                            </a>
                        </div>
                    </div>
                </td>
                <td>
                    {{ (int) $scan->totalfiles }}
                </td>
                <td class="text-success-emphasis fw-semibold">
                    {{ (int) $scan->files_new }}
                </td>
                <td class="text-warning-emphasis fw-bold">
                    {{ (int) $scan->files_modified }}
                </td>
                <td class="text-danger-emphasis fw-bold">
                    {{ (int) $scan->files_suspicious }}
                </td>
                <td class="d-none d-md-table-cell">
                    <a href="{{{ $backendUri->toString() }}}"
                       class="btn btn-outline-success"
                       target="_blank">
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_VIEW_REPORT')
                    </a>
                </td>
            </tr>
        @endforeach
        @if(empty($this->scans))
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