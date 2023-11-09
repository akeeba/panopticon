<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var mixed                                $context
 */

?>
<?php
$context = is_object($context) ? (array) $context : $context;
if (!is_array($context) || empty($context)) return;
?>
@if (isset($context['value']))
    @if (is_bool($context['value']))
        @if($context['value'])
            @lang('AWF_YES')
        @else
            @lang('AWF_NO')
        @endif
			<?php return ?>
    @elseif(is_scalar($context['value']))
        {{{ $context['value'] }}}
			<?php return ?>
    @elseif(is_array($context['value']))
			<?php $context = $context['value'] ?>
    @elseif(is_object($context['value']))
			<?php $context = (array) $context['value'] ?>
    @elseif (!isset($context['exception']))
			<?php return ?>
    @endif
@endif

@if (!empty($exception = ($context['exception'] ?? null)))
    <?php $exception = is_array($exception) ? $exception : (array) $exception ?>
    <details>
        <summary>
            <code>#{{{ $exception['code'] ?? 0 }}}.</code> {{{ $exception['message'] ?? '' }}}
        </summary>
        <pre>
                {{{ $exception['file'] ?? '???' }}}:{{{ $exception['line'] ?? '???' }}}
            {{{ $exception['trace'] }}}
            </pre>
    </details>
@else
    <table class="table">
        <tbody>
        @foreach($context as $k => $v)
            <tr>
                <th>{{{ $k }}}</th>
                <td>
                    @if (is_scalar($v))
                        {{{ $v }}}
                    @elseif(is_array($v))
                        {{{ print_r($v, true) }}}
                    @elseif(is_object($v))
                        {{{ print_r((array) $v, true) }}}
                    @else
                        (Not an array or object)
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif