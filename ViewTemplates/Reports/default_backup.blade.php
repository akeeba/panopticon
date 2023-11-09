<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */

$status        = $item->context->get('status');
$archive       = $item->context->get('context.archive');
$backupId      = $item->context->get('context.backupId');
$backupRecord  = $item->context->get('context.backupRecord');
$backupProfile = $item->context->get('backupProfile');
$errorContext  = $item->context->get('context');
?>

<div>
    @if($status)
        <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_BACKUP_TAKEN')
    @else
        <span class="fa fa-fw fa-xmark-circle text-danger" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_BACKUP_FAILED')
    @endif
</div>

<div class="d-flex flex-column flex-lg-row gap-1 gap-lg-3">
    @if ($backupProfile)
    <div>
        <span class="fa fa-fw fa-user-alt" aria-hidden="true"></span>
        @lang('PANOPTICON_SITES_LBL_AKEEBABACKUP_PROFILE')
        #{{{ $backupProfile }}}
    </div>
    @endif
    @if ($backupId)
    <div>
        <span class="fa fa-fw fa-hashtag" aria-hidden="true"></span>
        <span class="visually-hidden">#</span>
        {{{ $backupId }}}
    </div>
    @endif
</div>

@if (boolval($status) && $archive)
    <div>
        <span class="fa fa-fw fa-file-archive" aria-hidden="true"></span>
        <span class="visually-hidden">@lang('PANOPTICON_REPORTS_LBL_BACKUP_ARCHIVE')</span>
        {{{ $archive }}}
    </div>
@elseif(!boolval($status) && !empty($errorContext))
    <div>
        @include('Reports/error_cell', ['context' => $errorContext])
    </div>
@endif
