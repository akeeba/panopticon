<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Enumerations\CMSType;

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$returnUrl = $this->input->getBase64('returnurl', '');
$showExtUpdatesTab = $this->item->cmsType() === CMSType::JOOMLA && !empty($this->extUpdatePreferences);
?>

@if ($this->connectionError !== null)
    @include('Sites/troubleshoot')
@endif

<form action="@route('index.php?view=sites')" method="post" name="adminForm" id="adminForm">

    <div class="row mt-3 mb-4">
        <label for="name" class="col-sm-3 col-form-label fs-5 fw-bold">
            @lang('PANOPTICON_SITES_FIELD_NAME')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control fs-5 fw-medium" name="name" id="name"
                   value="{{{ $this->item->name ?? '' }}}" required
            >
        </div>
    </div>

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
            <button type="button" id="siteTabProperties"
                    class="nav-link" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentProperties" aria-controls="siteTabContentProperties">
                @lang('PANOPTICON_SITE_LBL_TAB_PROPERTIES')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="siteTabOtherFeatures"
                    class="nav-link" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentOtherFeatures" aria-controls="siteTabContentOtherFeatures">
                @lang('PANOPTICON_SITE_LBL_TAB_OTHER_FEATURES')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="siteTabNotes"
                    class="nav-link" aria-selected="true"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentNotes" aria-controls="siteTabContentNotes">
                @lang('PANOPTICON_SITE_LBL_NOTES_HEAD')
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" id="siteTabUpdate"
                    class="nav-link" aria-selected="false"
                    data-bs-toggle="tab" role="tab"
                    data-bs-target="#siteTabContentUpdate" aria-controls="siteTabContentUpdate">
                @lang('PANOPTICON_SITE_LBL_TAB_UPDATE_GENERIC')
            </button>
        </li>
        @if ($showExtUpdatesTab)
            <li class="nav-item" role="presentation">
                <button type="button" id="siteTabExtUpdate"
                        class="nav-link" aria-selected="false"
                        data-bs-toggle="tab" role="tab"
                        data-bs-target="#siteTabContentExtUpdate" aria-controls="siteTabContentExtUpdate">
                    @lang('PANOPTICON_SITE_LBL_TAB_EXTUPDATE')
                </button>
            </li>
        @endif
    </ul>

    <div class="tab-content container py-3" id="siteTabContent" tabindex="-1">
        <div class="tab-pane show active"
             id="siteTabContentConnection" role="tabpanel" aria-labelledby="siteTabConnection" tabindex="-1"
        >
            @include('Sites/form_connection')

            {{-- Server Identity --}}
            @include('Main/default_serverid')
        </div>
        <div class="tab-pane show"
             id="siteTabContentProperties" role="tabpanel" aria-labelledby="siteTabProperties" tabindex="-1"
        >
            @include('Sites/form_properties')
        </div>
        <div class="tab-pane show"
             id="siteTabContentOtherFeatures" role="tabpanel" aria-labelledby="siteTabOtherFeatures" tabindex="-1"
        >
            @include('Sites/form_other_features')
        </div>
        <div class="tab-pane show"
             id="siteTabContentNotes" role="tabpanel" aria-labelledby="siteTabNotes" tabindex="-1"
        >
            @include('Sites/form_notes')
        </div>
        <div class="tab-pane show"
             id="siteTabContentUpdate" role="tabpanel" aria-labelledby="siteTabUpdate" tabindex="-1"
        >
            @include('Sites/form_update')
        </div>
        @if ($showExtUpdatesTab)
        <div class="tab-pane show"
             id="siteTabContentExtUpdate" role="tabpanel" aria-labelledby="siteTabExtUpdate" tabindex="-1"
        >
            @include('Sites/form_extupdate')
        </div>
        @endif
    </div>

    <input type="hidden" name="id" value="{{{ $this->item->id ?? 0 }}}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
    @if (!empty($returnUrl))
        <input type="hidden" name="returnurl" value="{{{ $returnUrl }}}">
    @endif

</form>