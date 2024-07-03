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
                    <label class="form-check-label" for="login_failure_enable">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSWORD_HIBP')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PASSWORD_HIBP_HELP')
                </div>
            </div>
        </div>


    </div>
</div>