<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

/** @var \Akeeba\Panopticon\Model\Site $model */
$model               = $this->getModel();
$user                = $this->container->userManager->getUser();
$token               = $this->container->session->getCsrfToken()->getValue();
$config              = $model->getConfig();

?>

<h4 class="border-bottom border-info-subtle pb-1 mt-4 mb-2">
    @lang('PANOPTICON_SITE_LBL_SCANNER_HEAD')
</h4>

@if ($this->scans instanceof Throwable)
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
<?php return; ?>
@endif

<p class="small text-info mb-3">
    @lang('PANOPTICON_SITE_LBL_SCANNER_TIP')
</p>

<table class="table">
    <thead class="table-dark">
    <tr>
        <th rowspan="2">
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
    </tr>
    <tr>
        <th>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_TOTAL')
        </th>
        <th>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NEW')
        </th>
        <th>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_MODIFIED')
        </th>
        <th>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SUSPICIOUS')
        </th>
    </tr>
    </thead>
    <tbody>
    @foreach($this->scans as $scan)
    <?php
        $backendUri = new \Awf\Uri\Uri($model->getAdminUrl());
        $backendUri->setVar('option', 'com_admintools');
        $backendUri->setVar('view', 'Scanalerts');
        $backendUri->setVar('scan_id', (int) $scan->id);
    ?>
    <tr>
        <td>
            <a href="{{{ $backendUri->toString() }}}"
               target="_blank">
                {{ (int) $scan->id }}
            </a>
        </td>
        <td>
            <div class="d-flex flex-row gap-2">
                <div>
                    <div class="badge bg-secondary">
                        @if ($scan->origin === 'backend')
                            <span class="fa fa-desktop fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_BACKEND')</span>
                        @elseif ($scan->origin === 'cli')
                            <span class="fa fa-terminal fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_CLI')</span>
                        @elseif ($scan->origin === 'joomla')
                            <span class="fab fa-joomla fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_JOOMLA')</span>
                        @elseif ($scan->origin === 'api')
                            <span class="fa fa-code fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_PANOPTICON')</span>
                        @else
                            <span class="fa fa-question fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_SOURCE_UNKNOWN')</span>
                        @endif
                    </div>
                </div>
                <div class="{{ $scan->status === 'complete' ? 'text-success' : ($scan->status === 'run' ? 'text-warning' : 'text-danger') }}">
                    {{-- complete fail run --}}
                    @if ($scan->status === 'complete')
                        <span class="fa fa-check-circle fw-fw" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_COMPLETE')
                    @elseif ($scan->status === 'run')
                        <span class="fa fa-play-circle fw-fw" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_RUNNING')
                    @else
                        <span class="fa fa-exclamation-circle fw-fw" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SCAN_STATUS_FAILED')
                    @endif
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <span class="fw-semibold">
                    @if (!empty($scan->scanstart) && $scan->scanstart != '0000-00-00 00:00:00')
                    {{ \Awf\Html\Html::date($scan->scanstart, \Awf\Text\Text::_('DATE_FORMAT_LC7')) }}
                    @endif
                </span>
                <span class="text-muted">
                    @if (!empty($scan->scanend) && $scan->scanend != '0000-00-00 00:00:00')
                    {{ \Awf\Html\Html::date($scan->scanend, \Awf\Text\Text::_('DATE_FORMAT_LC7')) }}
                    @endif
                </span>
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