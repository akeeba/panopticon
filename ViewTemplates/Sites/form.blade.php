<?php
/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');
$returnUrl = $this->input->getBase64('returnurl', '');
?>

@if ($this->connectionError !== null)
    @include('Sites/troubleshoot')
@endif

<form action="@route('index.php?view=sites')" method="post" name="adminForm" id="adminForm" role="form">

    <ul class="nav nav-tabs" id="siteTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" id="siteTabConnection"
                    class="nav-link active" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentConnection" aria-controls="siteTabContentConnection">
                @lang('PANOPTICON_SITE_LBL_TAB_CONNECTION')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="siteTabUpdate"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentUpdate" aria-controls="siteTabContentUpdate">
                @lang('PANOPTICON_SITE_LBL_TAB_UPDATE')
            </button>
        </li>
    </ul>

    <div class="tab-content container py-3" id="siteTabContent" tabindex="-1">
        <div class="tab-pane show active"
             id="siteTabContentConnection" role="tabpanel" aria-labelledby="siteTabConnection" tabindex="-1"
        >
            @include('Sites/form_connection')
        </div>
        <div class="tab-pane show"
             id="siteTabContentUpdate" role="tabpanel" aria-labelledby="siteTabUpdate" tabindex="-1"
        >
            @include('Sites/form_update')
        </div>
    </div>

    <input type="hidden" name="id" value="{{ $this->item->id ?? 0 }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
    @if (!empty($returnUrl))
    <input type="hidden" name="returnurl" value="{{ $returnUrl }}">
    @endif

</form>