<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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