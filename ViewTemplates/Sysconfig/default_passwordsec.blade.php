<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_PASSWORD_SECURITY')</h3>
    <div class="card-body">
        {{--password_hibp--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[password_hibp]" id="password_hibp" value="1"
                            {{ $config->get('password_hibp', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="password_hibp">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSWORD_HIBP')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSWORD_HIBP_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3 mt-4">
            <div class="col-sm-9 offset-sm-3">
                <h4 class="h5">@lang('PANOPTICON_SYSCONFIG_LBL_PWRESET')</h4>
            </div>
        </div>

        {{-- pwreset --}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[pwreset]" id="pwreset" value="1"
                            {{ $config->get('pwreset', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET')
                    </label>
                </div>
            </div>
        </div>

        {{-- pwreset_mintime --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <label for="pwreset_mintime" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_MINTIME')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="pwreset_mintime" name="options[pwreset_mintime]"
                           value="{{{ $config->get('pwreset_mintime', 300) }}}"
                           min="0" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_SECONDS')
                    </div>
                </div>
            </div>
        </div>

        {{-- pwreset_maxfails --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <label for="pwreset_maxfails" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_MAXFAILS')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="pwreset_maxfails" name="options[pwreset_maxfails]"
                       value="{{{ $config->get('pwreset_maxfails', 3) }}}"
                       min="0" required
                >
            </div>
        </div>

        {{-- pwreset_mfa --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[pwreset_mfa]" id="pwreset_mfa"
                            {{ $config->get('pwreset_mfa', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_MFA')
                    </label>
                    <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_COMMON_SECURITY_NOTE')</div>
                </div>
            </div>
        </div>

        {{-- pwreset_passkeys --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[pwreset_passkeys]" id="pwreset_passkeys"
                            {{ $config->get('pwreset_passkeys', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_PASSKEYS')
                    </label>
                    <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_COMMON_SECURITY_NOTE')</div>
                </div>
            </div>
        </div>

        {{-- pwreset_superuser --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[pwreset_superuser]" id="pwreset_superuser"
                            {{ $config->get('pwreset_superuser', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_SUPERUSER')
                    </label>
                    <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_COMMON_SECURITY_NOTE')</div>
                </div>
            </div>
        </div>

        {{-- pwreset_admin --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[pwreset_admin]" id="pwreset_admin"
                            {{ $config->get('pwreset_admin', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_ADMIN')
                    </label>
                    <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_COMMON_SECURITY_NOTE')</div>
                </div>
            </div>
        </div>

        {{-- pwreset_groups --}}
        <div class="row mb-3" data-showon='[{"field":"options[pwreset]","values":["1"],"sign":"=","op":""}]'>
            <label for="pwreset_groups" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_GROUPS')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: $this->getModel()->getGroupsForSelect(includeEmpty: false),
                    name: 'pwreset_groups[]',
                    attribs: [
                        'class' => 'form-select js-choice',
                        'multiple' => 'multiple',
                    ],
                    selected: $config->get('pwreset_groups', [])
                ) }}
                <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PWRESET_GROUPS_HELP')</div>
            </div>
        </div>

        <div class="row mb-3 mt-4">
            <div class="col-sm-9 offset-sm-3">
                <h4 class="h5">@lang('PANOPTICON_MFA_HEAD_MFA_PAGE')</h4>
            </div>
        </div>

        {{--mfa_superuser--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mfa_superuser]" id="mfa_superuser" value="1"
                            {{ $config->get('mfa_superuser', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="mfa_superuser">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MFA_SUPERUSER')
                    </label>
                </div>
            </div>
        </div>

        {{--mfa_admin--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mfa_admin]" id="mfa_admin" value="1"
                            {{ $config->get('mfa_admin', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="mfa_admin">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MFA_ADMIN')
                    </label>
                </div>
            </div>
        </div>

        {{--mfa_force_groups--}}
        <div class="row mb-3">
            <label for="mfa_force_groups" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MFA_FORCE_GROUPS')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: $this->getModel()->getGroupsForSelect(includeEmpty: false),
                    name: 'mfa_force_groups[]',
                    attribs: [
                        'class' => 'form-select js-choice',
                        'multiple' => 'multiple',
                    ],
                    selected: $config->get('mfa_force_groups', [])
                ) }}
            </div>
        </div>

        {{--mfa_max_tries--}}
        <div class="row mb-3">
            <label for="mfa_max_tries" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MFA_MAX_TRIES')
            </label>
            <div class="col-sm-9">
                <input type="number" min="1" max="1000"
                       name="mfa_max_tries" id="mfa_max_tries"
                       class="form-control"
                       value="{{ (int) $config->get('mfa_max_tries', 3) ?: 3 }}" />
            </div>
        </div>

    </div>
</div>