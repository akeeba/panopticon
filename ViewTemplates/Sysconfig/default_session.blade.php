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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_SESSION')</h3>
    <div class="card-body">

        {{--session_save_levels--}}
        <div class="row mb-3">
            <label for="session_save_levels" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_SAVE_LEVELS')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="session_save_levels" name="options[session_save_levels]"
                       value="{{{ (int) $config->get('session_save_levels', 0) }}}"
                       min="0" max="5" required
                >
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_SAVE_LEVELS_HELP')
                </div>
            </div>
        </div>

        {{--session_encrypt--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[session_encrypt]" id="password_hibp" value="1"
                            {{ $config->get('session_encrypt', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="session_encrypt">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_ENCRYPT')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_ENCRYPT_HELP')
                </div>
            </div>
        </div>


    </div>
</div>