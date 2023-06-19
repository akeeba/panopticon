<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Awf\Uri\Uri;

$baseUri = Uri::getInstance($this->item->getBaseUrl());
$canEdit = $this->container->userManager->getUser()->getPrivilege('panopticon.admin');
$connectorVersion = $this->item->getConfig()->get('core.panopticon.version');
$connectorAPI = $this->item->getConfig()->get('core.panopticon.api');

?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->item->id }}</span>
    <span class="flex-grow-1">{{{ $this->item->name }}}</span>
    @if($canEdit)
    <a class="btn btn-secondary" role="button"
       href="@route(sprintf('index.php?view=site&id=%d&returnurl=%s', $this->item->id, base64_encode(Uri::getInstance()->toString())))">
        <span class="fa fa-pencil-alt"></span>
        <span class="visually-hidden">Edit</span>
    </a>
    @endif
</h3>

<div class="d-flex flex-column flex-md-row gap-1 gap-md-2">
    @if (!empty($connectorVersion))
    <div class="flex-md-grow-1 small text-muted">
        @sprintf('PANOPTICON_SITES_LBL_CONNECTOR_VERSION', $this->escape($connectorVersion))
    </div>
    @endif
    <div class="{{ empty($connectorVersion) ? 'flex-md-grow-1 text-end' : '' }}">
        <a href="{{{ $this->item->getBaseUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="{{ ($baseUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $baseUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $baseUri->toString(['user', 'pass', 'host', 'port', 'path', 'query', 'fragment']) }}}</span>
            <span class="fa fa-external-link-alt fa-xs text-muted small" aria-hidden="true"></span>
        </a>
    </div>
</div>

<div class="container my-3">
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6 order-1 order-lg-0">
            @include('Sites/item_joomlaupdate')
        </div>

        <div class="col-12 col-lg-6 order-0 order-lg-1">
            @include('Sites/item_php')
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_extensions')
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            @include('Sites/item_backup')
        </div>
    </div>

    {{-- TODO Admin Tools integration (https://github.com/akeeba/panopticon/issues/17) --}}
</div>