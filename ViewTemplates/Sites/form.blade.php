<?php
/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');
?>

@if ($this->connectionError !== null)
    @include('Sites/troubleshoot')
@endif

<form action="@route('index.php?view=sites')" method="post" name="adminForm" id="adminForm" role="form">

    <div class="row mb-3">
        <label for="name" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_SITES_FIELD_NAME')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="name" id="name"
                   value="{{{ $this->item->name ?? '' }}}" required
            >
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="enabled" name="enabled"
                       {{ $this->item->enabled ? 'checked' : '' }}
                >
                <label class="form-check-label" for="enabled">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
                </label>
            </div>
        </div>
    </div>

    <div class="alert alert-info col-sm-9 offset-sm-3">
        <h3 class="alert-heading fs-5 fw-semibold">
            <span class="fa fa-info-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_HEAD')
        </h3>
        <p>
            @lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_BODY')
        </p>
    </div>

    <div class="row mb-3">
        <label for="url" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_SITES_FIELD_URL')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="url" id="url"
                   value="{{{ $this->item->url ?? '' }}}" required
            >
        </div>
    </div>

    <div class="row mb-3">
        <label for="apiToken" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_SITES_FIELD_TOKEN')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control font-monospace" name="apiToken" id="url"
                   value="{{{ $config->get('config.apiKey') ?? '' }}}" required
            >
        </div>
    </div>

    <div class="col-sm-9 offset-sm-3">
        <button type="button" onclick="akeeba.System.submitForm('save')"
                class="btn btn-success">
            <span class="fa fa-save" aria-hidden="true"></span>
            @lang('PANOPTICON_BTN_SAVE')
        </button>
    </div>

    <input type="hidden" name="id" value="{{ $this->item->id ?? 0 }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
</form>