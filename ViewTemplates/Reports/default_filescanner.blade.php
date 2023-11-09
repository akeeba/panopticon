<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */

$status        = $item->context->get('status');
$errorContext  = $item->context->get('context');
?>

<div>
    @if($status)
        <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_FILESCANNER_SUCCESS')
    @else
        <span class="fa fa-fw fa-xmark-circle text-danger" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_FILESCANNER_FAILED')
    @endif
</div>

@if(!boolval($status) && !empty($errorContext))
    <div>
        @include('Reports/error_cell', ['context' => $errorContext])
    </div>
@endif