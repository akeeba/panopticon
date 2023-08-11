<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
{{--================================================================================================================--}}
{{-- System --}}
{{--================================================================================================================--}}

<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_SYSTEM')</h3>
    <div class="card-body">

        {{--live_site--}}
        <div class="row mb-3">
            <label for="live_site" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LIVE_SITE')
            </label>
            <div class="col-sm-9">
                <input type="url" class="form-control"
                       id="live_site" name="options[live_site]"
                       value="{{{ $config->get('live_site', \Awf\Uri\Uri::base()) }}}"
                       min="1" required
                >
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LIVE_SITE_HELP')
                </div>
            </div>
        </div>

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
                {{ \Awf\Html\Select::genericList(
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

        {{-- language --}}
        <div class="row mb-3">
            <label for="language" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LANGUAGE')
            </label>
            <div class="col-sm-9">
                {{ \Akeeba\Panopticon\Helper\Setup::languageOptions(
                    $config->get('language', 'en-GB'),
                    name: 'options[language]',
                    id: 'language',
                    attribs: ['class' => 'form-select']
                ) }}
            </div>
        </div>

        {{--timezone--}}
        <div class="row mb-3">
            <label for="timezone" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TIMEZONE')
            </label>
            <div class="col-sm-9">
                {{ \Akeeba\Panopticon\Helper\Setup::timezoneSelect(
                    $config->get('timezone', 'UTC'),
                    name: 'options[timezone]',
                    id: 'timezone'
                ) }}
            </div>
        </div>

        {{--error_reporting--}}
        <div class="row mb-3">
            <label for="error_reporting" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_ERROR_REPORTING')
            </label>
            <div class="col-sm-9">
                {{ \Awf\Html\Select::genericList(
                    data: [
                        'default' => 'PANOPTICON_SYSCONFIG_OPT_ERROR_REPORTING_DEFAULT',
                        'none' => 'PANOPTICON_SYSCONFIG_OPT_ERROR_REPORTING_NONE',
                        'simple' => 'PANOPTICON_SYSCONFIG_OPT_ERROR_REPORTING_SIMPLE',
                        'maximum' => 'PANOPTICON_SYSCONFIG_OPT_ERROR_REPORTING_MAXIMUM',
                    ],
                    name: 'options[error_reporting]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('error_reporting', 'default'),
                    idTag: 'error_reporting',
                    translate: true
                ) }}
            </div>
        </div>

        {{--debug--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[debug]" id="debug"
                            {{ $config->get('debug', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="debug">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DEBUG')
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>