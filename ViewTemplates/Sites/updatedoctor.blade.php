<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Awf\Uri\Uri;

$baseUri  = Uri::getInstance($this->item->getBaseUrl());
$adminUri = Uri::getInstance($this->item->getAdminUrl());
$results  = $this->updateDoctorResults;
$favIcon  = $this->item->getFavicon(asDataUrl: true, onlyIfCached: true);

?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->item->id }}</span>
    @if($favIcon)
        <img src="{{{ $favIcon }}}"
             style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
             class="mx-1 p-1 border rounded"
             alt="">
    @endif
    <span class="flex-grow-1">{{{ $this->item->name }}}</span>
    @if($this->canEdit)
        <a class="btn btn-secondary" role="button"
           href="@route(sprintf('index.php?view=site&id=%d&returnurl=%s', $this->item->id, base64_encode(Uri::getInstance()->toString())))">
            <span class="fa fa-pencil-alt"></span>
            <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
        </a>
    @endif
</h3>
<div class="d-flex flex-column flex-md-row gap-1 gap-md-2">
    <div class="flex-md-grow-1 d-flex flex-column">
        <a href="{{{ $this->item->getBaseUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-users fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($baseUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $baseUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $baseUri->toString(['user', 'pass', 'host', 'port', 'path', 'query', 'fragment']) }}}</span>
            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
        </a>
        <a href="{{{ $this->item->getAdminUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-user-secret fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($adminUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $adminUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $adminUri->toString(['user', 'pass', 'host', 'port', 'path']) }}}</span>
            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
        </a>
    </div>
</div>

@if ($this->updateDoctorError !== null)
    {{-- The doctor itself blew up --}}
    <div class="alert alert-danger my-3">
        <h3 class="alert-heading h5">
            <span class="fa fa-triangle-exclamation" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_SELF_ERROR')
        </h3>
        <p class="mb-0">{{{ $this->updateDoctorError->getMessage() }}}</p>
    </div>
@elseif ($results === null)
    <div class="alert alert-warning my-3">
        @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_NO_RESULTS')
    </div>
@elseif ($results->apiUnreachable)
    {{-- The JSON API is down; the Connection Doctor is the right tool first --}}
    <div class="card border-warning my-3">
        <h3 class="card-header bg-warning text-dark">
            <span class="fa fa-plug-circle-xmark" aria-hidden="true"></span>
            @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_API_DOWN_HEAD')
        </h3>
        <div class="card-body">
            <p>@lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_API_DOWN_BODY')</p>
            <a href="@route(sprintf('index.php?view=site&task=connectionDoctor&id=%d&%s=1', $this->item->id, $this->container->session->getCsrfToken()->getValue()))"
               class="btn btn-primary" role="button">
                <span class="fa fa-stethoscope" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_CONNECTION_DOCTOR_TITLE')
            </a>
        </div>
    </div>
@elseif ($results->ok)
    {{-- Everything checks out --}}
    <div class="px-4 py-5 mt-0 mb-4 text-center">
        <div class="mx-auto mb-4">
            <span class="badge bg-success rounded-5 p-2">
                <span class="far fa-check-circle display-5" aria-hidden="true"></span>
            </span>
        </div>
        <h3 class="display-5 fw-bold text-success">
            @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_OKAY')
        </h3>
        <div class="col-lg-6 mx-auto">
            <p class="lead mb-4">
                @lang('PANOPTICON_SITES_LBL_UPDATE_DOCTOR_OKAY_MORE')
            </p>
        </div>
    </div>
    @include('Sites/troubleshoot_update', ['results' => $results, 'forceDebug' => false])
@else
    @include('Sites/troubleshoot_update', ['results' => $results, 'forceDebug' => true])
@endif
