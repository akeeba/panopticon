<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

/** @var \Akeeba\Panopticon\Model\Site $model */
$model               = $this->getModel();
$user                = $this->container->userManager->getUser();
$token               = $this->container->session->getCsrfToken()->getValue();
$config              = $model->getConfig();

?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-shield-halved" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_HEAD') <small class="text-muted">@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_SUBHEAD')</small>
        </span>
        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardAdminToolsBody"
                aria-expanded="true" aria-controls="cardAdminToolsBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body collapse show" id="cardAdminToolsBody">
        @if ($this->hasAdminTools && !$this->hasAdminToolsPro)
            <div class="alert alert-info fs-5">
                <h4 class="alert-heading">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_CORE')
                </h4>
                <p>@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NEED_PRO')</p>
                <p>@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_AFTER_INSTALL')</p>
                <p>
                    <a href="@route(sprintf(
                                'index.php?view=site&task=refreshExtensionsInformation&id=%d&%s=1',
                                $model->getId(),
                                $token
                            ))"
                       class="btn btn-primary" role="button">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_BTN_RELOAD')
                    </a>
                </p>
            </div>
        @elseif(!$this->hasAdminTools)
            <div class="alert alert-info">
                <h4 class="alert-heading fs-5">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NONE')
                </h4>
                <p>@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_NEED_PRO')</p>
                <p>@lang('PANOPTICON_SITE_LBL_ADMINTOOLS_AFTER_INSTALL')</p>
                <p>
                    <a href="@route(sprintf(
                                'index.php?view=site&task=refreshExtensionsInformation&id=%d&%s=1',
                                $model->getId(),
                                $token
                            ))"
                       class="btn btn-primary" role="button">
                        <span class="fa fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_ADMINTOOLS_BTN_RELOAD')
                    </a>
                </p>
            </div>
        @else
            @include('Sites/item_admintools_actions')
            @include('Sites/item_admintools_scanner')
        @endif
    </div>
</div>