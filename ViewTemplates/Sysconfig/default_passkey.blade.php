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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_PASSKEY')</h3>
    <div class="card-body">
        {{--passkey_login--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[passkey_login]" id="passkey_login" value="1"{{ $config->get('passkey_login', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="passkey_login">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN')
                    </label>
                </div>
            </div>
        </div>

        {{--passkey_login_no_mfa--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[passkey_login_no_mfa]" id="passkey_login_no_mfa" value="1"{{ $config->get('passkey_login_no_mfa', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="passkey_login_no_mfa">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_NO_MFA')
                    </label>
                </div>
            </div>
        </div>

        {{--passkey_login_no_password--}}
        <div class="row mb-3">
            <label for="passkey_login_no_password" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_NO_PASSWORD')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
						'always' => $this->getContainer()->language->text('AWF_YES'),
						'user' => $this->getContainer()->language->text('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_NO_PASSWORD_OPT_USER'),
						'never' => $this->getContainer()->language->text('AWF_NO')
],
                    name: 'passkey_login_force_groups[]',
                    attribs: [
                        'class' => 'form-select',
                    ],
                    selected: $config->get('passkey_login_no_password', 'user')
                ) }}
            </div>
        </div>

        {{--passkey_login_force_groups--}}
        <div class="row mb-3">
            <label for="passkey_login_force_groups" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_FORCE_GROUPS')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: $this->getModel()->getGroupsForSelect(includeEmpty: false),
                    name: 'passkey_login_force_groups[]',
                    attribs: [
                        'class' => 'form-select js-choice',
                        'multiple' => 'multiple',
                    ],
                    selected: $config->get('passkey_login_force_groups', [])
                ) }}
            </div>
        </div>

        {{--passkey_login_force_superuser--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[passkey_login_force_superuser]" id="passkey_login_force_superuser" value="1"{{ $config->get('passkey_login_force_superuser', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="passkey_login_force_superuser">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_FORCE_SUPERUSER')
                    </label>
                </div>
            </div>
        </div>

        {{--passkey_login_force_admin--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[passkey_login_force_admin]" id="passkey_login_force_admin" value="1"{{ $config->get('passkey_login_force_admin', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="passkey_login_force_admin">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSKEY_LOGIN_FORCE_ADMIN')
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>
