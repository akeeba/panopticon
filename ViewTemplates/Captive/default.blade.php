<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Captive\Html $this
 * @var \Akeeba\Panopticon\Model\Captive     $model
 */

$model = $this->getModel();
?>

<h3 class="d-flex flex-row">
    <span class="flex-grow-1">
        @if (!$this->allowEntryBatching)
            {{{ $this->record->title }}}
        @else
            {{{ $this->getModel()->translateMethodName($this->record->method) }}}
        @endif
    </span>
    @if (!empty($this->renderOptions['help_url']))
        <span class="flex-shrink-1">
        <a
                href="<?= $this->renderOptions['help_url'] ?>"
                class="btn btn-sm btn-secondary"
                target="_blank"
                title="@lang('PANOPTICON_MFA_LBL_HELP')"
        >
            <span class="fa fa-question-circle" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_MFA_LBL_HELP')</span>
        </a>
    </span>
    @endif
</h3>

@if ($this->renderOptions['pre_message'])
    <div class="">
        {{ $this->renderOptions['pre_message'] }}
    </div>
@endif

<form action="@route('index.php?view=captive&task=validate&record_id=' . $this->record->id)"
      method="post"
>
    @if ($this->renderOptions['field_type'] == 'custom')
        {{ $this->renderOptions['html'] }}
    @else
        <div class="row mb-3">
            @if ($this->renderOptions['label'])
                <label for="mfaCode"
                       class="col-sm-3 col-form-label">
                    {{ $this->renderOptions['label'] }}
                </label>
            @endif
            <div class="col-sm-9 <?= $this->renderOptions['label'] ? '' : 'offset-sm-3' ?>">
                <input type="<?= $this->renderOptions['input_type'] ?>"
                       name="code"
                       class="form-control"
                       value=""
                       @if (!empty($this->renderOptions['placeholder']))
                           placeholder="<?= $this->renderOptions['placeholder'] ?>"
                       @endif
                       id="mfaCode"
                >
            </div>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3 d-flex gap-3 align-items-center">
            <button class="btn btn-lg btn-primary px-5"
                    style="{{ $this->renderOptions['hide_submit'] ? 'display: none' : '' }}"
                    type="submit">
                <span class="fa fa-unlock-keyhole" aria-hidden="true"></span>
                @lang('PANOPTICON_MFA_LBL_VALIDATE')
            </button>

            @if (count($this->records) > 1)
                <a href="@route('index.php?view=captive&task=select')"
                   class="btn btn-outline-secondary"
                >
                    <span class="fa fa-key" aria-hidden="true"></span>
                    @lang('PANOPTICON_MFA_LBL_USEDIFFERENTMETHOD')
                </a>
            @endif

            <a href="@route('index.php?view=login&task=logout')"
               class="btn btn-outline-danger"
               id="loginguard-captive-button-logout">
                <span class="fa fa-right-from-bracket" aria-hidden="true"></span>
                @lang('PANOPTICON_MFA_LBL_LOGOUT')
            </a>
        </div>
    </div>

    <input type="hidden" name="@token" value="1">
</form>

@if ($this->renderOptions['post_message'])
    <div class="">
        {{ $this->renderOptions['post_message'] }}
    </div>
@endif

@if ($this->renderOptions['field_type'] !== 'custom')
    <script type="text/javascript">
        (() =>
        {
            const elCodeField = document.getElementById("mfaCode");
            if (!elCodeField)
            {
                return;
            }
            elCodeField.focus();
        })();
    </script>
@endif