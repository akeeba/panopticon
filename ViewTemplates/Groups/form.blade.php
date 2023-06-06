<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Groups\Html $this
 * @var \Akeeba\Panopticon\Model\Groups     $model
 */
$model      = $this->getModel();
$privileges = $model->getPrivileges();
$token      = $this->container->session->getCsrfToken()->getValue();

?>
<form action="@route('index.php?view=groups')" method="post" name="adminForm" id="adminForm" role="form">
    <div class="row mb-3">
        <label for="title" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_GROUPS_FIELD_TITLE')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="title" id="title"
                   value="{{{ $model->title ?? '' }}}" required
            >
        </div>
    </div>

    <div class="row mb-3">
        <label for="permissions" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_GROUPS_FIELD_PERMISSIONS')
        </label>
        <div class="col-sm-9" id="permissions">
            <div class="w-100 d-flex flex-column gap-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="permissions[panopticon.view]"
                           {{ in_array('panopticon.view', $privileges) ? 'checked' : '' }}
                           id="permissions_view">
                    <label class="form-check-label" for="permissions_view">@lang('PANOPTICON_PRIVILEGE_VIEW')</label>
                    <div class="form-text">@lang('PANOPTICON_PRIVILEGE_VIEW_HELP')</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="permissions[panopticon.run]"
                           {{ in_array('panopticon.run', $privileges) ? 'checked' : '' }}
                           id="permissions_run">
                    <label class="form-check-label" for="permissions_run">@lang('PANOPTICON_PRIVILEGE_RUN')</label>
                    <div class="form-text">@lang('PANOPTICON_PRIVILEGE_RUN_HELP')</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="permissions[panopticon.admin]"
                           {{ in_array('panopticon.admin', $privileges) ? 'checked' : '' }}
                           id="permissions_admin">
                    <label class="form-check-label" for="permissions_admin">@lang('PANOPTICON_PRIVILEGE_ADMIN')</label>
                    <div class="form-text">@lang('PANOPTICON_PRIVILEGE_ADMIN_HELP')</div>
                </div>

            </div>
        </div>
    </div>

    <input type="hidden" name="id" value="{{ (int) $model->id ?? 0 }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
</form>