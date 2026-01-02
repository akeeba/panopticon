<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Mfamethods\Html $this */

$cancelURL = $this->container->router->route('index.php?view=users&task=edit&id=' . $this->user->getId());

if (!empty($this->returnURL))
{
	$cancelURL = $this->escape(base64_decode($this->returnURL));
}

$recordId = (int) ($this->record->id ?? 0);
$method   = $this->record->method ?? $this->getModel()->getState('method');
$userId   = $this->user->getId() ?? 0;

?>

<form action="@route(sprintf('index.php?view=mfamethod&task=save&id=%d&method=%s&user_id=%d', $recordId, $method, $userId))"
      id="mfa-method-edit" name="mfa-method-edit"
      method="post">

    <div class="row mb-3">
        <label for="method-edit-title" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MFA_LBL_EDIT_FIELD_TITLE')
        </label>
        <div class="col-sm-9">
            <input type="text"
                   id="method-edit-title"
                   class="form-control"
                   name="title"
                   value="{{{ $this->record->title }}}">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is-default-method" {{ $this->record->default ? 'checked' : '' }} name="default">
                <label class="form-check-label" for="is-default-method">
                    @lang('PANOPTICON_MFA_LBL_EDIT_FIELD_DEFAULT')
                </label>
            </div>
        </div>
    </div>

	@if (!empty($this->renderOptions['pre_message']))
    <div class="row mb-3">
        {{ $this->renderOptions['pre_message'] }}
    </div>
	@endif

	@if (!empty($this->renderOptions['tabular_data']))
    <div class="method-edit-tabular-container mb-3">
        @if (!empty($this->renderOptions['table_heading']))
        <h4>
            <?= $this->renderOptions['table_heading'] ?>
        </h4>
		@endif
        <table>
            <tbody class="table">
            @foreach ($this->renderOptions['tabular_data'] as $cell1 => $cell2)
            <tr>
                <th scope="row">
                    {{ $cell1 }}
                </th>
                <td>
                    {{ $cell2 }}
                </td>
            </tr>
			@endforeach
            </tbody>
        </table>
    </div>
	@endif

	@if ($this->renderOptions['field_type'] == 'custom')
		<div class="mb-3">
            {{ $this->renderOptions['html'] }}
        </div>
	@else
        <div class="row mb-3">
            @if ($this->renderOptions['label'])
            <label class="col-sm-3 col-form-label" for="method-edit-code">
                {{ $this->renderOptions['label'] }}
            </label>
            @endif
            <div class="col-sm-9 {{ $this->renderOptions['label'] ? '' : 'offset-sm-3' }}">
                <input type="{{ $this->renderOptions['input_type'] }}"
                       class="form-control" id="method-edit-code"
                       <?= ($this->renderOptions['autocomplete'] ?? null) ? sprintf('autocomplete="%s"', $this->renderOptions['autocomplete']) : '' ?>
                       name="code"
                       value="{{{ $this->renderOptions['input_value'] }}}"
                       placeholder="{{{ $this->renderOptions['placeholder'] }}}">
            </div>
        </div>
	@endif

    <div id="method-edit-buttons" class="row mb-3">
        <div class="col-sm-9 offset-sm-3 d-flex flex-row gap-3 align-items-center">
	        @if ($this->renderOptions['show_submit'] || $this->isEditExisting)
                <div>
                    <button type="submit" class="btn btn-primary btn-lg px-5" id="mfa-register-submit">
                        <span class="fa fa-check-circle" aria-hidden="true"></span>
                        @lang('PANOPTICON_MFA_LBL_EDIT_SUBMIT')
                    </button>
                </div>
	        @endif

            @if ($this->renderOptions['field_type'] === 'custom')
                {{ $this->renderOptions['html_button'] ?? '' }}
            @endif

            <div>
                <a href="<?= $cancelURL ?>" role="button"
                   class="btn btn-outline-danger px-5">
                    <span class="fa fa-cancel" aria-hidden="true"></span>
                    @lang('PANOPTICON_MFA_LBL_EDIT_CANCEL')
                </a>
            </div>
        </div>
    </div>

	@if (!empty($this->renderOptions['post_message']))
    <div class="method-edit-post-message mb-3">
        {{ $this->renderOptions['post_message'] }}
    </div>
	@endif

    <input type="hidden" name="@token" value="1">
    @if (!empty($this->returnURL))
        <input type="hidden" name="returnurl" value="{{{ $this->returnURL }}}">
    @endif
    @foreach ($this->renderOptions['hidden_data'] ?? [] as $key => $value)
        <input type="hidden" name="{{{ $key }}}" value="{{{ $value }}}">
    @endforeach

</form>