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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_PROXY')</h3>
    <div class="card-body">

        {{-- proxy_enabled --}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[proxy_enabled]" id="proxy_enabled"
                            {{ $config->get('proxy_enabled', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="proxy_enabled">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_ENABLED')
                    </label>
                </div>
            </div>
        </div>

        {{-- proxy_host --}}
        <div class="row mb-3">
            <label for="proxy_host" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_HOST')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="proxy_host" name="options[proxy_host]"
                       value="{{{ $config->get('proxy_host', 'localhost') }}}"
                       required
                >
            </div>
        </div>

        {{-- proxy_port --}}
        <div class="row mb-3">
            <label for="proxy_port" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_PORT')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="proxy_port" name="options[proxy_port]"
                       value="{{{ (int) $config->get('proxy_port', '3128') }}}"
                       min="0" max="65535" required
                >
            </div>
        </div>

        {{-- proxy_user --}}
        <div class="row mb-3">
            <label for="proxy_user" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_USER')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="proxy_user" name="options[proxy_user]"
                       value="{{{ $config->get('proxy_user', '') }}}"
                >
            </div>
        </div>

        {{-- proxy_pass --}}
        <div class="row mb-3">
            <label for="proxy_pass" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_PASS')
            </label>
            <div class="col-sm-9">
                <input type="password" class="form-control" id="proxy_pass" name="options[proxy_pass]"
                       value="{{{ $config->get('proxy_pass', '') }}}"
                >
            </div>
        </div>

        {{-- proxy_no --}}
        <div class="row mb-3">
            <label for="proxy_no" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_NO')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="proxy_no" name="options[proxy_no]"
                       value="{{{ $config->get('proxy_no', '') }}}"
                >
                <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PROXY_NO_HELP')</div>
            </div>
        </div>

    </div>
</div>
