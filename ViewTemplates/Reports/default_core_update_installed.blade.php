<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */
$success      = $item->context->get('success');
$oldVersion   = $item->context->get('oldVersion');
$newVersion   = $item->context->get('newVersion');
$startTime    = $item->context->get('start_time');
$endTime      = $item->context->get('end_time');
$duration     = (!empty($startTime) && !empty($endTime)) ? ($endTime - $startTime) : null;
$hasBackup    = $item->context->get('backup_on_update');
$errorContext = $item->context->get('context');
?>

@if ($success)
    <div>
        <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_INSTALLED_SUCCESS')
    </div>
@else
    <div>
        <span class="fa fa-fw fa-xmark-circle text-danger" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_INSTALLED_FAILURE')
    </div>
    <div>
        @include('Reports/error_cell', ['context' => $errorContext])
    </div>
@endif
<div class="d-flex flex-column flex-lg-row gap-1 gap-lg-3">
    @if (!empty($oldVersion) && !empty($newVersion))
        <span class="text-info">
            {{{ $oldVersion }}}
            <span class="fa fa-arrow-right" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
            {{{ $newVersion }}}
        </span>
    @endif
    @if ($duration)
        <span>
            <span class="fa fa-fw fa-clock" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_REPORTS_LBL_DURATION_SR')</span>
            {{ $this->timeAgo($startTime, $endTime, autoSuffix: false) }}
        </span>
    @endif
    @if ($hasBackup)
        <span>
            <span class="fa fa-fw fa-archive" aria-hidden="true"></span>
            @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_INSTALLED_BACKUP')
        </span>
    @endif
</div>