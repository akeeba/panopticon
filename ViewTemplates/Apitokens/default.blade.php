<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Apitokens\Html $this */

?>

{{-- API Endpoint URL --}}
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">
            <span class="fa fa-link" aria-hidden="true"></span>
            @lang('PANOPTICON_APITOKENS_LBL_API_URL')
        </h5>
        <div class="input-group mb-2">
            <input type="text" class="form-control font-monospace" value="{{ $this->apiUrl }}" readonly id="apiUrl">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="navigator.clipboard.writeText(document.getElementById('apiUrl').value)">
                <span class="fa fa-copy" aria-hidden="true"></span>
            </button>
        </div>
        <p class="card-text text-muted small">
            @lang('PANOPTICON_APITOKENS_LBL_API_URL_NOTE')
        </p>
    </div>
</div>

{{-- Security Warning --}}
<div class="alert alert-warning">
    <span class="fa fa-triangle-exclamation" aria-hidden="true"></span>
    @lang('PANOPTICON_APITOKENS_LBL_SECURITY_WARNING')
</div>

{{-- Create Token Form --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md">
                <label for="newTokenDescription" class="form-label">
                    @lang('PANOPTICON_APITOKENS_LBL_NEW_TOKEN_DESCRIPTION')
                </label>
                <input type="text" class="form-control" id="newTokenDescription"
                       placeholder="@lang('PANOPTICON_APITOKENS_LBL_DESCRIPTION')">
            </div>
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-primary" id="btnCreateToken">
                    <span class="fa fa-plus" aria-hidden="true"></span>
                    @lang('PANOPTICON_APITOKENS_BTN_CREATE')
                </button>
            </div>
        </div>
    </div>
</div>

{{-- New Token Display (hidden by default) --}}
<div class="alert alert-success d-none" id="newTokenAlert">
    <p class="fw-bold">@lang('PANOPTICON_APITOKENS_MSG_CREATED')</p>
    <div class="input-group">
        <input type="text" class="form-control font-monospace" id="newTokenValue" readonly>
        <button class="btn btn-outline-success" type="button" id="btnCopyNewToken">
            <span class="fa fa-copy" aria-hidden="true"></span>
            @lang('PANOPTICON_APITOKENS_BTN_COPY_TOKEN')
        </button>
    </div>
</div>

{{-- Token Table --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0" id="tokenTable">
            <thead>
            <tr>
                <th>@lang('PANOPTICON_APITOKENS_LBL_DESCRIPTION')</th>
                <th class="text-center">@lang('PANOPTICON_APITOKENS_LBL_ENABLED')</th>
                <th>@lang('PANOPTICON_APITOKENS_LBL_CREATED_ON')</th>
                <th class="text-center">@lang('PANOPTICON_APITOKENS_LBL_ACTIONS')</th>
            </tr>
            </thead>
            <tbody id="tokenTableBody">
            @if (empty($this->tokens))
                <tr id="noTokensRow">
                    <td colspan="4" class="text-center text-muted py-4">
                        @lang('PANOPTICON_APITOKENS_LBL_NO_TOKENS')
                    </td>
                </tr>
            @else
                @foreach ($this->tokens as $token)
                    <tr data-token-id="{{ $token->id }}">
                        <td>{{ $token->description ?: '—' }}</td>
                        <td class="text-center">
                            <button class="btn btn-sm {{ $token->enabled ? 'btn-success' : 'btn-secondary' }} btn-toggle-token"
                                    data-id="{{ $token->id }}">
                                <span class="fa {{ $token->enabled ? 'fa-circle-check' : 'fa-circle-xmark' }}"
                                      aria-hidden="true"></span>
                                {{ $token->enabled
                                    ? $this->getLanguage()->text('PANOPTICON_APITOKENS_BTN_DISABLE')
                                    : $this->getLanguage()->text('PANOPTICON_APITOKENS_BTN_ENABLE') }}
                            </button>
                        </td>
                        <td>{{ \Awf\Html\Html::date($token->created_on, $this->getLanguage()->text('DATE_FORMAT_LC2')) }}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-info btn-show-token" data-id="{{ $token->id }}"
                                        title="@lang('PANOPTICON_APITOKENS_BTN_SHOW_TOKEN')">
                                    <span class="fa fa-eye" aria-hidden="true"></span>
                                </button>
                                <button class="btn btn-outline-danger btn-delete-token" data-id="{{ $token->id }}"
                                        title="@lang('PANOPTICON_APITOKENS_BTN_DELETE')">
                                    <span class="fa fa-trash-can" aria-hidden="true"></span>
                                </button>
                            </div>
                            <div class="input-group input-group-sm mt-1 d-none token-value-group" data-id="{{ $token->id }}">
                                <input type="text" class="form-control font-monospace token-value-input" readonly>
                                <button class="btn btn-outline-secondary btn-copy-token" type="button">
                                    <span class="fa fa-copy" aria-hidden="true"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
    </div>
</div>
