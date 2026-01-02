<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
{{--================================================================================================================--}}
{{-- Automation --}}
{{--================================================================================================================--}}

<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_AUTOMATION')</h3>
    <div class="card-body">
        {{--webcron_key--}}
        <div class="row mb-3">
            <label for="webcron_key" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_WEBCRON_KEY')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control font-monospace" id="webcron_key" name="options[webcron_key]"
                       value="{{{ $config->get('webcron_key', '') }}}"
                       minlength="8" maxlength="128"
                       required
                >
            </div>
        </div>

        {{--cron_stuck_threshold--}}
        <div class="row mb-3">
            <label for="cron_stuck_threshold" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CRON_STUCK_THRESHOLD')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="cron_stuck_threshold" name="options[cron_stuck_threshold]"
                           value="{{{ $config->get('cron_stuck_threshold', 3) }}}"
                           min="3" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_MINUTES')
                    </div>
                </div>
            </div>
        </div>

        {{--max_execution--}}
        <div class="row mb-3">
            <label for="max_execution" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MAX_EXECUTION')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="max_execution" name="options[max_execution]"
                           value="{{{ $config->get('max_execution', 60) }}}"
                           min="5" max="3600" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_SECONDS')
                    </div>
                </div>
            </div>
        </div>

        {{--execution_bias--}}
        <div class="row mb-3">
            <label for="execution_bias" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_EXECUTION_BIAS')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="execution_bias" name="options[execution_bias]"
                           value="{{{ $config->get('execution_bias', 75) }}}"
                           min="15" max="100" required
                    >
                    <div class="input-group-text">
                        %
                    </div>
                </div>
            </div>
        </div>

        {{--accurate_php_cli--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[accurate_php_cli]" id="accurate_php_cli" value="1"
                            {{ $config->get('accurate_php_cli', 1) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="accurate_php_cli">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_ACCURATE_PHP_CLI')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_ACCURATE_PHP_CLI_HELP')
                </div>
            </div>
        </div>

    </div>
</div>