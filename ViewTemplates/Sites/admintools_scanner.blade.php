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
            Status
        </th>
        <th rowspan="2">
            Date &amp; Time
        </th>
        <th colspan="4" class="text-center">
            Files
        </th>
    </tr>
    <tr>
        <th>
            Total
        </th>
        <th>
            New
        </th>
        <th>
            Modified
        </th>
        <th>
            Suspicious
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
                            <span class="visually-hidden">Backend</span>
                        @elseif ($scan->origin === 'cli')
                            <span class="fa fa-terminal fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">CLI</span>
                        @elseif ($scan->origin === 'joomla')
                            <span class="fab fa-joomla fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">Joomla!&trade; CLI</span>
                        @elseif ($scan->origin === 'api')
                            <span class="fa fa-code fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">Panopticon</span>
                        @else
                            <span class="fa fa-question fw-fw" aria-hidden="true"></span>
                            <span class="visually-hidden">Unknown</span>
                        @endif
                    </div>
                </div>
                <div class="{{ $scan->status === 'complete' ? 'text-success' : ($scan->status === 'run' ? 'text-warning' : 'text-danger') }}">
                    {{-- complete fail run --}}
                    @if ($scan->status === 'complete')
                        <span class="fa fa-check-circle fw-fw" aria-hidden="true"></span>
                        Complete
                    @elseif ($scan->status === 'run')
                        <span class="fa fa-play-circle fw-fw" aria-hidden="true"></span>
                        Running
                    @else
                        <span class="fa fa-exclamation-circle fw-fw" aria-hidden="true"></span>
                        Failed
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