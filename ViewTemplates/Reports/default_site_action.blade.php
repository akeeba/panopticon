<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */

$status  = $item->context->get('status');
$context = $item->context->get('context');
?>

<div>
    @if($status)
        <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"></span>
    @else
        <span class="fa fa-fw fa-xmark-circle text-danger" aria-hidden="true"></span>
    @endif
    {{ $item->siteActionAsString() }}
</div>
<div>
    @include('Reports/error_cell', ['context' => $context])
</div>
