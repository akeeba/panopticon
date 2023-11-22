<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Users\Html $this
 * @var \Akeeba\Panopticon\Model\Users     $model
 */
$model      = $this->getModel();
$user       = $model->getId()
    ? $this->container->userManager->getUser($model->getId())
    : new \Akeeba\Panopticon\Library\User\User();
$token      = $this->container->session->getCsrfToken()->getValue();

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-choice').forEach((element) => {
        new Choices(element, {allowHTML: false, removeItemButton: true, placeholder: true, placeholderValue: ""});
    });
});

JS;

?>
@js('choices/choices.min.js', $this->getContainer()->application)
@inlinejs($js)

<form action="@route('index.php?view=users')" method="post"
      name="adminForm" id="adminForm"
      class="row g-2"
>
    <div class="row g-2">
        <div class="col-12 col-lg-6">
            <div class="card card-body">
                <p class="card-title fs-5 fw-semibold mt-1 mb-3">
                    Basic information
                </p>

                <div class="row my-2">
                    <label for="username" class="col-sm-3 col-form-label">
                        @lang('PANOPTICON_USERS_LBL_FIELD_USERNAME')
                    </label>
                    <div class="col-sm-9">
                        {{-- You can only edit the username when you are a Superuser --}}
                        <input type="text" class="form-control" name="username" id="username"
                               value="{{{ $model->username ?? '' }}}" required
                               {{ $this->container->userManager->getUser()->getPrivilege('panopticon.super') ? '' : 'disabled' }}
                        >
                    </div>
                </div>

                <div class="row my-2">
                    <label for="name" class="col-sm-3 col-form-label">
                        @lang('PANOPTICON_USERS_LBL_FIELD_name')
                    </label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" name="name" id="name"
                               value="{{{ $model->name ?? '' }}}" required
                        >
                    </div>
                </div>

                <div class="row my-2">
                    <label for="email" class="col-sm-3 col-form-label">
                        @lang('PANOPTICON_USERS_LBL_FIELD_EMAIL')
                    </label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" name="email" id="email"
                               value="{{{ $model->email ?? '' }}}" required
                        >
                        <div class="form-text">
                            @lang('PANOPTICON_USERS_LBL_FIELD_EMAIL_HELP')
                        </div>
                    </div>
                </div>

                <div class="row my-2">
                    <label for="password" class="col-sm-3 col-form-label">
                        @lang('PANOPTICON_USERS_LBL_FIELD_PASSWORD')
                    </label>
                    <div class="col-sm-9">
                        <input type="password" class="form-control" name="password" id="password"
                               value=""
                        >
                        @if($model->getId())
                            <div class="form-text">
                                Leave empty if you do not wish to change the password of this user account.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="row my-2">
                    <label for="password2" class="col-sm-3 col-form-label">
                        @lang('PANOPTICON_USERS_LBL_FIELD_PASSWORD2')
                    </label>
                    <div class="col-sm-9">
                        <input type="password" class="form-control" name="password2" id="password2"
                               value=""
                        >
                    </div>
                </div>

                {{-- You can only edit groups when you're a Superuser --}}
                @if ($this->container->userManager->getUser()->getPrivilege('panopticon.super'))
                    {{-- Groups--}}
                    <div class="row mb-3">
                        <label for="groups" class="col-sm-3 col-form-label">
                            @lang('PANOPTICON_USERS_LBL_FIELD_GROUPS')
                        </label>
                        <div class="col-sm-9">
                            {{ $this->container->html->select->genericList(
                                data: array_merge([(object) [
                                    'value' => '',
                                    'text' => $this->getLanguage()->text('PANOPTICON_SITES_LBL_GROUPS_PLACEHOLDER')
                                ]], $this->getModel()->getGroupsForSelect()),
                                name: 'groups[]',
                                attribs: [
                                    'class' => 'form-select js-choice',
                                    'multiple' => 'multiple',
                                ],
                                selected: $user->getParameters()->get('usergroups', [])
                            ) }}
                        </div>
                    </div>

                @endif

            </div>
        </div>

        {{-- You can only edit privileges when you're a Superuser --}}
        @if ($this->container->userManager->getUser()->getPrivilege('panopticon.super'))
        <div class="col-12 col-lg-6">
            <div class="card card-body">
                <fieldset>
                    <legend class="card-title fs-5 fw-semibold mt-1 mb-4">
                        @lang('PANOPTICON_USERS_LBL_FIELD_PERMISSIONS')
                    </legend>
                    <div class="w-100 d-flex flex-column gap-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.super]" value="1"
                                {{ $user->getPrivilege('panopticon.super') ? 'checked' : '' }}
                                id="permissions_super">
                            <label class="form-check-label" for="permissions_super">@lang('PANOPTICON_PRIVILEGE_SUPER')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_SUPER_HELP')</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.admin]" value="1"
                                {{ $user->getPrivilege('panopticon.admin') ? 'checked' : '' }}
                                id="permissions_admin">
                            <label class="form-check-label" for="permissions_admin">@lang('PANOPTICON_PRIVILEGE_ADMIN')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_ADMIN_HELP')</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.view]" value="1"
                                {{ $user->getPrivilege('panopticon.view') ? 'checked' : '' }}
                                id="permissions_view">
                            <label class="form-check-label" for="permissions_view">@lang('PANOPTICON_PRIVILEGE_VIEW')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_VIEW_HELP')</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.run]" value="1"
                                {{ $user->getPrivilege('panopticon.run') ? 'checked' : '' }}
                                id="permissions_run">
                            <label class="form-check-label" for="permissions_run">@lang('PANOPTICON_PRIVILEGE_RUN')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_RUN_HELP')</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.addown]" value="1"
                                {{ $user->getPrivilege('panopticon.addown') ? 'checked' : '' }}
                                id="permissions_addown">
                            <label class="form-check-label" for="permissions_addown">@lang('PANOPTICON_PRIVILEGE_ADDOWN')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_ADDOWN_HELP')</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="permissions[panopticon.editown]" value="1"
                                {{ $user->getPrivilege('panopticon.editown') ? 'checked' : '' }}
                                id="permissions_editown">
                            <label class="form-check-label" for="permissions_editown">@lang('PANOPTICON_PRIVILEGE_EDITOWN')</label>
                            <div class="form-text">@lang('PANOPTICON_PRIVILEGE_EDITOWN_HELP')</div>
                        </div>
                    </div>
                </fieldset>

            </div>

            {{-- TODO Multi-factor Authentication Administration --}}
        </div>
        @endif
    </div>

    @if ($this->canEditMFA)
    <div class="row g-2">
        <div class="col-12">
            @include('Users/form_mfa')
        </div>
    </div>
    @endif

    <input type="hidden" name="id" value="{{ $model->getId() ?? 0 }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">

</form>