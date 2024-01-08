<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$token               = $this->container->session->getCsrfToken()->getValue();

?>
@section('atUnblockMyIP')
    <div>
        <a href="@route(sprintf(
            'index.php?view=site&task=admintoolsUnblockMyIp&id=%d&%s=1',
            $this->item->getId(), $token
        ))"
           class="btn btn-success" role="button">
            <span class="fa fa-user-nurse" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_UNBLOCK')
        </a>
    </div>
@stop

@section('atEnableDisablePlugin')
    <div>
        @unless ($this->siteConfig->get('core.admintools.renamed'))
            <a href="@route(sprintf(
                'index.php?view=site&task=admintoolsPluginDisable&id=%d&%s=1',
                $this->item->getId(), $token
            ))"
               class="btn btn-warning" role="button"
               data-bs-toggle="tooltip" data-bs-placement="bottom"
               data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_PLUGIN_DISABLE_TIP')"
            >
                <span class="fa fa-toggle-off" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_PLUGIN_DISABLE')
            </a>
        @else
            <a href="@route(sprintf(
                'index.php?view=site&task=admintoolsPluginEnable&id=%d&%s=1',
                $this->item->getId(), $token
            ))"
               class="btn btn-success" role="button"
               data-bs-toggle="tooltip" data-bs-placement="bottom"
               data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_PLUGIN_ENABLE_TIP')"
            >
                <span class="fa fa-toggle-on" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_PLUGIN_ENABLE')
            </a>
        @endunless
    </div>
@stop

@section('atHtaccessEnableDisable')
    <div>
        <a href="@route(sprintf(
                'index.php?view=site&task=admintoolsHtaccessDisable&id=%d&%s=1',
                $this->item->getId(), $token
            ))"
           class="btn btn-outline-secondary" role="button"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_HTACCESS_DISABLE_TIP')"
        >
            <span class="fa fa-file-export" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_HTACCESS_DISABLE')
        </a>
    </div>
    <div>
        <a href="@route(sprintf(
                'index.php?view=site&task=admintoolsHtaccessEnable&id=%d&%s=1',
                $this->item->getId(), $token
            ))"
           class="btn btn-outline-dark" role="button"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_HTACCESS_ENABLE_TIP')"
        >
            <span class="fa fa-file-export" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_HTACCESS_ENABLE')
        </a>
    </div>
@stop

<fieldset class="mx-1 my-2 p-2 border rounded-2">
    <legend class="visually-hidden">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_ACTIONS')</legend>
    <p class="text-info small mb-2 px-2">
        <span class="fa fa-door-closed" aria-hidden="true"></span>
        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_ACTIONS_TIP')
    </p>
    <div class="row row-cols-lg-auto g-4 align-items-center mb-3 p-2">
        @yield('atUnblockMyIP')
        @yield('atEnableDisablePlugin')
        @yield('atHtaccessEnableDisable')
    </div>
</fieldset>
