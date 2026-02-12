<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;

/**
 * @var \Akeeba\Panopticon\View\Extensioninstall\Html $this
 */

$token = $this->container->session->getCsrfToken()->getValue();

?>
@js('media://js/extensioninstall.js')

{{-- Warning Box --}}
<div class="alert alert-danger my-3">
    <h4 class="alert-heading">
        <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
        @lang('PANOPTICON_EXTENSIONINSTALL_LBL_WARNING_HEAD')
    </h4>
    <p class="mb-0">
        @lang('PANOPTICON_EXTENSIONINSTALL_LBL_WARNING_BODY')
    </p>
</div>

<h4>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_STEP_SELECT')</h4>

{{-- Site selection form --}}
<form action="@route('index.php?view=extensioninstall&task=review')" method="post"
      name="adminForm" id="adminForm">

    {{-- Filters --}}
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="d-flex flex-column flex-lg-row gap-2 gap-lg-3 justify-content-center align-items-center">
            {{-- Search --}}
            <div class="input-group pnp-mw-50">
                <input type="search" class="form-control form-control-lg" id="search"
                       placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                       name="search" value="{{{ $this->getModel('site')->getState('search', '') }}}">
                <label for="search" class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
                <button type="submit" class="btn btn-primary">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</span>
                </button>
            </div>
        </div>
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_EXTENSIONINSTALL_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-1">
                <input type="checkbox" id="checkAll"
                       onclick="akeeba.ExtensionInstall.toggleAll(this.checked)">
                <label for="checkAll" class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </label>
            </th>
            <th>
                @lang('PANOPTICON_EXTENSIONINSTALL_LBL_SITE_NAME')
            </th>
            <th>
                @lang('PANOPTICON_EXTENSIONINSTALL_LBL_CMS_TYPE')
            </th>
            <th>
                @lang('PANOPTICON_EXTENSIONINSTALL_LBL_CMS_VERSION')
            </th>
            <th>
                @lang('PANOPTICON_EXTENSIONINSTALL_LBL_PHP_VERSION')
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->items as $site)
            <?php
            /** @var \Akeeba\Panopticon\Model\Site $site */
            $user = $this->container->userManager->getUser();
            $canInstall = $user->authorise('panopticon.admin', $site)
                || $user->authorise('panopticon.run', $site);
            if (!$canInstall) continue;
            $config = $site->getConfig();
            ?>
            <tr>
                <td>
                    <input type="checkbox" class="extensioninstall-site-cb"
                           id="cb{{{ $site->getId() }}}"
                           value="{{{ $site->getId() }}}"
                           data-site-id="{{{ $site->getId() }}}"
                           onchange="akeeba.ExtensionInstall.onCheckboxChange()">
                    <label for="cb{{{ $site->getId() }}}" class="visually-hidden">
                        @sprintf('PANOPTICON_EXTENSIONINSTALL_LBL_SELECT_SITE', $site->name)
                    </label>
                </td>
                <td>
                    <a class="fw-medium"
                       href="@route(sprintf('index.php?view=site&task=read&id=%s', $site->getId()))">
                        {{{ $site->name }}}
                    </a>
                    <div class="small mt-1">
                        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                        <a href="{{{ $site->getBaseUrl() }}}" class="link-secondary text-decoration-none"
                           target="_blank">
                            {{{ $site->getBaseUrl() }}}
                            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
                        </a>
                    </div>
                    @if (!empty($groups = $config->get('config.groups')))
                        <div>
                            @foreach($groups as $gid)
                                @if (isset($this->groupMap[$gid]))
                                    <span class="badge bg-secondary">
                                        {{{ $this->groupMap[$gid] }}}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </td>
                <td>
                    @if ($site->cmsType() === CMSType::JOOMLA)
                        <span class="fab fa-fw fa-joomla text-secondary" aria-hidden="true"></span>
                        Joomla!
                    @elseif ($site->cmsType() === CMSType::WORDPRESS)
                        <span class="fab fa-fw fa-wordpress text-info" aria-hidden="true"></span>
                        WordPress
                    @endif
                </td>
                <td>
                    {{{ $config->get('core.current.version') }}}
                </td>
                <td>
                    <span class="fab fa-fw fa-php text-primary" aria-hidden="true"></span>
                    {{{ $config->get('core.php') }}}
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

    <input type="hidden" name="site_ids" id="site_ids" value="">
    <input type="hidden" name="task" id="task" value="review">
    <input type="hidden" name="token" value="@token()">
</form>
