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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_LOGINFAIL')</h3>
    <div class="card-body">
        {{--login_failure_enable--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[login_failure_enable]" id="login_failure_enable" value="1"
                            {{ $config->get('login_failure_enable', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="login_failure_enable">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_FAILURE_ENABLE')
                    </label>
                </div>
            </div>
        </div>

        {{--mfa_counts_as_login_failure--}}
        <div class="row mb-3" data-showon='[{"field":"options[login_failure_enable]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mfa_counts_as_login_failure]" id="mfa_counts_as_login_failure" value="1"
                            {{ $config->get('mfa_counts_as_login_failure', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="mfa_counts_as_login_failure">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MFA_COUNTS_AS_LOGIN_FAILURE')
                    </label>
                </div>
            </div>
        </div>

        {{-- login_max_failures --}}
        <div class="row mb-3" data-showon='[{"field":"options[login_failure_enable]","values":["1"],"sign":"=","op":""}]'>
            <label for="login_max_failures" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_MAX_FAILURES')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="login_max_failures" name="options[login_max_failures]"
                           value="{{{ $config->get('login_max_failures', 5) }}}"
                           min="1" required
                    >
                </div>
            </div>
        </div>

        {{-- login_failure_window --}}
        <div class="row mb-3" data-showon='[{"field":"options[login_failure_enable]","values":["1"],"sign":"=","op":""}]'>
            <label for="login_failure_window" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_FAILURE_WINDOW')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="login_failure_window" name="options[login_failure_window]"
                           value="{{{ $config->get('login_failure_window', 60) }}}"
                           min="1" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_SECONDS')
                    </div>
                </div>
            </div>
        </div>

        {{-- login_lockout --}}
        <div class="row mb-3" data-showon='[{"field":"options[login_failure_enable]","values":["1"],"sign":"=","op":""}]'>
            <label for="login_lockout" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_LOCKOUT')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="login_lockout" name="options[login_lockout]"
                           value="{{{ $config->get('login_lockout', 900) }}}"
                           min="1" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_SECONDS')
                    </div>
                </div>
            </div>
        </div>

        {{-- login_lockout_extend --}}
        <div class="row mb-3" data-showon='[{"field":"options[login_failure_enable]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[login_lockout_extend]" id="login_lockout_extend"
                            {{ $config->get('login_lockout_extend', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="login_lockout_extend">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_LOCKOUT_EXTEND')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOGIN_LOCKOUT_EXTEND_HELP')
                </div>
            </div>
        </div>
    </div>
</div>