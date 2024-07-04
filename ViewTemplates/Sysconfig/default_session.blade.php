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
        {{--session_timeout--}}
        <div class="row mb-3">
            <label for="session_timeout" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_TIMEOUT')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="session_timeout" name="options[session_timeout]"
                           value="{{{ $config->get('session_timeout', 1440) }}}"
                           min="1" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_MINUTES')
                    </div>
                </div>
            </div>
        </div>

        {{-- session_token_algorithm --}}
        <div class="row mb-3">
            <label for="session_token_algorithm" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_TOKEN_ALGORITHM')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'sha512' => 'PANOPTICON_SYSCONFIG_OPT_SHA512',
                        'sha384' => 'PANOPTICON_SYSCONFIG_OPT_SHA384',
                        'sha256' => 'PANOPTICON_SYSCONFIG_OPT_SHA256',
                        'sha224' => 'PANOPTICON_SYSCONFIG_OPT_SHA224',
                        'sha1' => 'PANOPTICON_SYSCONFIG_OPT_SHA1',
                        'md5' => 'PANOPTICON_SYSCONFIG_OPT_MD5',
                    ],
                    name: 'options[session_token_algorithm]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('session_token_algorithm', 'sha512'),
                    idTag: 'session_token_algorithm',
                    translate: true
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_TOKEN_ALGORITHM_HELP')
                </div>
            </div>
        </div>

        {{--session_use_default_path--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[session_use_default_path]" id="session_use_default_path" value="1"
                            {{ $config->get('session_use_default_path', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="session_use_default_path">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_USE_DEFAULT_PATH')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SESSION_USE_DEFAULT_PATH_HELP')
                </div>
            </div>
        </div>

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
                    <input class="form-check-input" type="checkbox" name="options[session_encrypt]" id="session_encrypt" value="1"
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