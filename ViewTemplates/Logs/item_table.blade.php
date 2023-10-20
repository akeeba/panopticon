<?php

defined('AKEEBA') || die;

use Awf\Text\Text;

/**
 * @var \Akeeba\Panopticon\View\Logs\Html $this
 */

$timeFormat = 'j M y H:i:s T';
$hasFacility = array_reduce($this->logLines, fn(bool $carry, object $item) => $carry || !empty($item->facility), false);
?>

<table class="table table-striped table-hover">
    <thead>
    <tr>
        <th>
            @lang('PANOPTICON_LOGS_LBL_TIME')
        </th>
        <th>
            @lang('PANOPTICON_LOGS_LBL_LEVEL')
        </th>
        @if ($hasFacility)
        <th>
            @lang('PANOPTICON_LOGS_LBL_FACILITY')
        </th>
        @endif
        <th>
            @lang('PANOPTICON_LOGS_LBL_MESSAGE')
        </th>
    </tr>
    </thead>
    <tbody class="table-group-divider">
    @foreach($this->logLines as $item)
			<?php
			$textClass = match($item->loglevel) {
				'emergency' => 'text-danger-emphasis fw-bolder',
				'alert' => 'text-danger-emphasis fw-bold',
				'critical' => 'text-danger-emphasis fw-semibold',
				'error' => 'text-danger',
				'warning' => 'text-warning-emphasis fw-semibold',
				'notice' => 'text-warning',
				'debug' => 'text-text-tertiary fw-light',
				default => '',
			};

			$icon = match($item->loglevel) {
				'emergency' => 'fa-skull-crossbones',
				'alert' => 'fa-bomb',
				'critical' => 'fa-bell',
				'error' => 'fa-circle-exclamation',
				'warning' => 'fa-triangle-exclamation',
				'notice' => 'fa-message',
				'debug' => 'fa-bug-slash',
				default => 'fa-info-circle',
			};
			?>
        <tr class="{{ $textClass }}">
            <td class="{{ $textClass }}" style="white-space: nowrap">
                @html('basic.date', $item->timestamp->format(DATE_RFC7231), $timeFormat, false)
            </td>
            <td class="{{ $textClass }}">
                <span class="fa fa-fw {{ $icon }} me-2" aria-hidden="true"></span>
                @lang('PANOPTICON_LOGS_LBL_PRIORITY_' . $item->loglevel)
            </td>
            @if ($hasFacility)
                <td class="{{ $textClass }}">
                    {{{ $item->facility }}}
                </td>
            @endif
            <td class="{{ $textClass }}">
                <div>
                    {{{ $item->message }}}
                </div>
                @if (!empty($item->context))
                    <details>
                        <summary class="btn btn-outline-secondary btn-sm">@lang('PANOPTICON_LOGS_LBL_CONTEXT')</summary>
                        <pre class="my-1 p-2 border bg-body-tertiary small">{{{ json_encode($item->context, JSON_PRETTY_PRINT) }}}</pre>
                    </details>
                @endif
                @if (!empty($item->extra))
                    <details>
                        <summary class="btn btn-outline-secondary btn-sm">@lang('PANOPTICON_LOGS_LBL_EXTRA')</summary>
                        <pre>{{{ json_encode($item->extra, JSON_PRETTY_PRINT) }}}</pre>
                    </details>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>