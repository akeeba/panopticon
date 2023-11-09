<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */

$success      = $item->context->get('success');
$oldVersion   = $item->context->get('oldVersion');
$newVersion   = $item->context->get('newVersion');
$errorContext = $item->context->get('context');
?>

@if ($success)
    <div>
        <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_LBL_EXT_UPDATE_INSTALLED_SUCCESS')
    </div>
@else
    <div>
        <span class="fa fa-fw fa-xmark-circle text-danger" aria-hidden="true"></span>
        @lang('PANOPTICON_REPORTS_EXT_CORE_UPDATE_INSTALLED_FAILURE')
    </div>
    <div>
        @yieldRepeatable('renderErrorContext', $errorContext)
    </div>
@endif

<div>
    {{{ $item->context->get('extension.name') }}}
    <span class="small font-monospace text-muted">({{{ $item->context->get('extension.key') }}})</span>
</div>
@if (!empty($oldVersion) && !empty($newVersion))
    <div class="text-info">
        {{{ $oldVersion }}}
        <span class="fa fa-arrow-right" aria-hidden="true"></span>
        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
        {{{ $newVersion }}}
    </div>
@endif