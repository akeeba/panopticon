<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Awf\Uri\Uri;

$favIcon = $this->item->getFavicon(asDataUrl: true, onlyIfCached: true);
?>

@include('Main/webpush_prompt')

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->item->id }}</span>
    @if($favIcon)
        <img src="{{{ $favIcon }}}"
             style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
             class="mx-1 p-1 border rounded"
             alt="">
    @endif
    <span class="flex-grow-1">{{{ $this->item->name }}}</span>
    @if (!empty($this->siteConfig->get('whois')))
        @include('Sites/item_whois')
    @endif
    @if (!empty($this->siteConfig->get('ssl')))
        @include('Sites/item_ssl')
    @endif
    @if($this->canEdit)
        <a class="btn btn-secondary" role="button"
           href="@route(sprintf('index.php?view=site&id=%d&returnurl=%s', $this->item->id, base64_encode(Uri::getInstance()->toString())))">
            <span class="fa fa-pencil-alt" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
        </a>
    @endif
</h3>

<div class="d-flex flex-column flex-md-row gap-1 gap-md-2">
    @if (!empty($this->connectorVersion))
        <div class="flex-md-grow-1 small text-muted d-flex flex-column">
            <div>
                @sprintf('PANOPTICON_SITES_LBL_CONNECTOR_VERSION', $this->escape($this->connectorVersion))
            </div>
            @if ($this->connectorAPI)
            <div>
                @sprintf('PANOPTICON_SITES_LBL_CONNECTOR_API', (int) $this->connectorAPI)
            </div>
            @endif
        </div>
    @endif
    <div class="{{ empty($this->connectorVersion) ? 'flex-md-grow-1 text-end' : '' }} d-flex flex-column">
        <a href="{{{ $this->item->getBaseUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-users fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($this->baseUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $this->baseUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $this->baseUri->toString(['user', 'pass', 'host', 'port', 'path', 'query', 'fragment']) }}}</span>
            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
        </a>
        <a href="{{{ $this->item->getAdminUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-user-secret fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($this->adminUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $this->adminUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $this->adminUri->toString(['user', 'pass', 'host', 'port', 'path']) }}}</span>@if(!empty($this->adminUri->getQuery()))<span class="text-body-tertiary">{{{ $this->adminUri->toString(['query', 'fragment']) }}}</span>@endif
            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
        </a>
    </div>
</div>

{{-- Show group labels --}}
@if (!empty($groups = $this->item->getGroups(true)))
<div class="my-1">
    <span class="fa fa-fw fa-user-group text-secondary" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_GROUPS')</span>
    @foreach($groups as $groupName)
        <span class="badge bg-secondary">
            {{{ $groupName }}}
        </span>
    @endforeach
</div>
@endif

<div class="container my-3">
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6 order-1 order-lg-0">
            @if ($this->item->cmsType() === CMSType::JOOMLA)
                {{-- Joomla! sites: Joomla!&reg; Update information --}}
                @include('Sites/item_joomlaupdate')
            @elseif ($this->item->cmsType() === CMSType::WORDPRESS)
                {{-- WordPress sites: WordPress update information --}}
                @include('Sites/item_wpupdate')
            @endif
        </div>

        <div class="col-12 col-lg-6 order-0 order-lg-1">
            @include('Sites/item_php')
        </div>
    </div>

    @if($this->hasCollectedServerInfo())
    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_server')
        </div>
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12">
            @if ($this->item->cmsType() === CMSType::JOOMLA)
                {{-- Joomla! sites --}}
                @include('Sites/item_extensions')
            @elseif ($this->item->cmsType() === CMSType::WORDPRESS)
                {{-- WordPress sites --}}
                @include('Sites/item_wpplugins')
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_backup')
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_admintools')
        </div>
    </div>

    @if ($this->item->cmsType() === CMSType::JOOMLA)
    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_corechecksums')
        </div>
    </div>
    @endif

    @if($this->canEdit)
        <div class="row g-3 mb-3">
            <div class="col-12">
                @include('Sites/item_notes')
            </div>
        </div>
    @endif
</div>