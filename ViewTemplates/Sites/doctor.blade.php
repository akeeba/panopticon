<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Exception\AkeebaBackup\AkeebaBackupNotInstalled;
use Akeeba\Panopticon\Model\Exception\AkeebaBackupIsNotPro;
use Awf\Uri\Uri;

$baseUri              = Uri::getInstance($this->item->getBaseUrl());
$adminUri             = Uri::getInstance($this->item->getAdminUrl());
$config               = $this->item->getConfig();
$connectorVersion     = $config->get('core.panopticon.version');
$connectorAPI         = $config->get('core.panopticon.api');
$hasAkeebaBackupError = $this->akeebaBackupConnectionError instanceof Throwable
                        && !$this->akeebaBackupConnectionError instanceof AkeebaBackupNotInstalled
                        && !$this->akeebaBackupConnectionError instanceof AkeebaBackupIsNotPro;

?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->item->id }}</span>
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
    @if (!empty($connectorVersion))
        <div class="flex-md-grow-1 small text-muted d-flex flex-column">
            <div>
                @sprintf('PANOPTICON_SITES_LBL_CONNECTOR_VERSION', $this->escape($connectorVersion))
            </div>
            @if ($connectorAPI)
                <div>
                    @sprintf('PANOPTICON_SITES_LBL_CONNECTOR_API', (int) $connectorAPI)
                </div>
            @endif
        </div>
    @endif
    <div class="{{ empty($connectorVersion) ? 'flex-md-grow-1 text-end' : '' }} d-flex flex-column">
        <a href="{{{ $this->item->getBaseUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-users fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($baseUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $baseUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $baseUri->toString(['user', 'pass', 'host', 'port', 'path', 'query', 'fragment']) }}}</span>
            <span class="fa fa-external-link-alt fa-xs text-muted small" aria-hidden="true"></span>
        </a>
        <a href="{{{ $this->item->getAdminUrl() }}}" target="_blank" class="text-decoration-none">
            <span class="fa fa-user-secret fa-fw text-secondary me-1" aria-hidden="true"></span>
            <span class="{{ ($adminUri->getScheme() === 'https') ? 'text-muted' : 'text-danger' }}">{{{ $adminUri->getScheme() }}}://</span><span
                    class="fw-medium">{{{ $adminUri->toString(['user', 'pass', 'host', 'port', 'path']) }}}</span>@if(!empty($adminUri->getQuery()))<span class="text-body-tertiary">{{{ $adminUri->toString(['query', 'fragment']) }}}</span>@endif
            <span class="fa fa-external-link-alt fa-xs text-muted small" aria-hidden="true"></span>
        </a>
    </div>
</div>

@if ($this->connectionError !== null)
    @include('Sites/troubleshoot', [
        'forceDebug' => true,
        'border' => 'border-0',
        'background' => '',
        'showHeader' => false,
    ])
@endif

@if ($this->connectionError === null && !$hasAkeebaBackupError)
    <div class="px-4 py-5 mt-0 mb-4 text-center">
        <div class="mx-auto mb-4">
			<span class="badge bg-success rounded-5 p-2">
				<span class="far fa-check-circle display-5" aria-hidden="true"></span>
			</span>
        </div>

        <h3 class="display-5 fw-bold text-success">
            @lang('PANOPTICON_SITES_LBL_CONNECTION_DOCTOR_OKAY')
        </h3>
        <div class="col-lg-6 mx-auto">
            <p class="lead mb-4">
                @lang('PANOPTICON_SITES_LBL_CONNECTION_DOCTOR_OKAY_MORE')
            </p>
        </div>
    </div>
@endif

@if ($this->akeebaBackupConnectionError !== null)
    @include('Sites/troubleshoot_akeebabackup')
@endif
