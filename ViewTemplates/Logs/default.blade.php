<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Logs\Html $this
 * @var \Akeeba\Panopticon\Model\Log $model
 */

$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();
?>

@repeatable('logFileName', $fileName)
<?php
$isArchived = str_ends_with($fileName, '.gz');
[$prefix, $suffix] = explode('.log', $fileName);
$suffix = '.log' . $suffix;
?>
@if (!$isArchived)
    <a href="@route(sprintf('index.php?view=log&task=read&logfile=%s', urlencode($fileName)))"
       class="text-decoration-none">
@endif
<span class="{{ $isArchived ? 'text-muted' : 'fw-bold' }}">{{{ $prefix }}}</span><span
        class="{{ $isArchived ? 'text-body-tertiary' : 'text-muted' }}">{{{ $suffix }}}</span>
@if (!$isArchived)
    </a>
@endif
@endrepeatable

@repeatable('status', $fileName)
<?php
$isArchived = str_ends_with($fileName, '.gz');
?>
@if ($isArchived)
    <span class="fa fa-fw fa-archive text-body-tertiary" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="bottom"
          data-bs-title="@lang('PANOPTICON_LOGS_LBL_STATUS_ARCHIVED')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_STATUS_ARCHIVED')</span>
@else
    <span class="fa fa-fw fa-file-text text-success" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="bottom"
          data-bs-title="@lang('PANOPTICON_LOGS_LBL_STATUS_ACTIVE')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_STATUS_ACTIVE')</span>
@endif
@endrepeatable

@repeatable('actions', $fileName)
<?php
$isArchived = str_ends_with($fileName, '.gz');
$token = $this->container->session->getCsrfToken()->getValue();
?>

@if (!$isArchived)
    <a href="@route(sprintf('index.php?view=log&task=read&logfile=%s', urlencode($fileName)))"
        class="btn btn-sm btn-primary">
        <span class="fa fa-fw fa-eye" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="bottom"
              data-bs-title="@lang('PANOPTICON_LOGS_LBL_VIEW')"
        ></span>
        <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_VIEW') {{{ $fileName }}}</span>
    </a>
@else
    <button type="button" disabled
            class="btn btn-sm btn-primary">
        <span class="fa fa-fw fa-eye-slash" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="bottom"
              data-bs-title="@lang('PANOPTICON_LOGS_LBL_NOVIEW_HELP')"
        ></span>
        <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_VIEW') {{{ $fileName }}}</span>
    </button>
@endif

<a href="@route(sprintf('index.php?view=log&task=download&logfile=%s&%s=1', urlencode($fileName), $token))"
   class="btn btn-sm btn-secondary">
        <span class="fa fa-fw fa-download" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="bottom"
              data-bs-title="@lang('PANOPTICON_LOGS_LBL_DOWNLOAD')"
        ></span>
    <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_DOWNLOAD') {{{ $fileName }}}</span>
</a>

<a href="@route(sprintf('index.php?view=log&task=delete&logfile=%s&%s=1', urlencode($fileName), $token))"
   class="btn btn-sm btn-danger">
        <span class="fa fa-fw fa-trash" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="bottom"
              data-bs-title="@lang('PANOPTICON_LOGS_LBL_DELETE')"
        ></span>
    <span class="visually-hidden">@lang('PANOPTICON_LOGS_LBL_DELETE') {{{ $fileName }}}</span>
</a>

@endrepeatable

<form action="@route('index.php?view=logs')" method="post" name="adminForm" id="adminForm">
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="my-2 d-flex flex-row justify-content-center p-2">
	        <div class="input-group pnp-mw-50">
                <input type="search" class="form-control form-control-lg" id="search"
                       placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                       name="search" value="{{{ $model->getState('search', '') }}}">
                <label for="search" class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
                <button type="submit"
                        class="btn btn-primary">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
                </button>
            </div>
        </div>
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <div>
                <label class="visually-hidden" for="site_id">@lang('PANOPTICON_LOGS_LBL_FIELD_SITE')</label>
                {{
                   $this->container->helper->setup->siteSelect(
	                   selected: $model->getState('site_id') ?? '',
	                   name: 'site_id',
	                   attribs: [
						   'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                       ],
                       withSystem: false
	               )
                }}
            </div>
            <div>
                <label class="visually-hidden" for="archived">@lang('PANOPTICON_LOGS_LBL_STATUS_ARCHIVED')</label>
                {{ $this->container->html->select->genericList( [
                    '0' => 'PANOPTICON_LOGS_LBL_ARCHIVED_OPT_ACTIVE',
                    '1' => 'PANOPTICON_LOGS_LBL_ARCHIVED_OPT_ALL',
                ], 'archived', [
                    'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                ], selected: $model->getState('archived', 1),
                idTag: 'archived',
                translate: true) }}
            </div>
        </div>
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_SITES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-15">
                @lang('PANOPTICON_LOGS_LBL_FIELD_ACTIONS')
            </th>
            <th class="pnp-w-5">
                @lang('PANOPTICON_LOGS_LBL_FIELD_STATUS')
            </th>
            <th>
                @lang('PANOPTICON_LOGS_LBL_FIELD_FILE')
            </th>
            <th>
                @lang('PANOPTICON_LOGS_LBL_FIELD_SITE')
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->items as $logName)
            <tr>
                <td>
                    @yieldRepeatable('actions', $logName)
                </td>
                <td class="text-center">
                    @yieldRepeatable('status', $logName)
                </td>
                <td>
                    <div class="d-flex flex-column flex-lg-row gap-2 gap-lg-3">
                        <div class="flex-grow-1">
                            @yieldRepeatable('logFileName', $logName)
                        </div>
                        <div class="font-monospace">
                            {{ $this->filesize($logName) }}
                        </div>
                    </div>
                </td>
                <td>
                    <?php $siteId = $this->getSiteIdFromFilename($logName) ?>
                    @if (empty($siteId))
                        <span class="fa fa-robot text-muted" aria-hidden="true"></span>
                        <span class="fw-medium">@lang('PANOPTICON_LOGS_LBL_SYSTEM')</span>
                    @else
                        <span class="fa fa-globe text-body-tertiary" aria-hidden="true"></span>
                        <span class="text-secondary">#{{ (int) $siteId }}.</span>
                        <span class="fw-medium">{{{ $this->siteNames[$siteId] ?? '???' }}}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        @if (!count($this->items))
            <tr>
                <td colspan="20" class="text-center text-body-tertiary">
                    @lang('AWF_PAGINATION_LBL_NO_RESULTS')
                </td>
            </tr>
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td colspan="20" class="center">
                {{ $this->pagination->getListFooter(['class' => 'form-select akeebaGridViewAutoSubmitOnChange']) }}
            </td>
        </tr>
        </tfoot>
    </table>

    <input type="hidden" name="boxchecked" id="boxchecked" value="0">
    <input type="hidden" name="task" id="task" value="browse">
    <input type="hidden" name="token" value="@token()">
</form>