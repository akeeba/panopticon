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

        {{-- language --}}
        <div class="row mb-3">
            <label for="language" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LANGUAGE')
            </label>
            <div class="col-sm-9">
                {{ $this->getContainer()->helper->setup->languageOptions(
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
                {{ $this->getContainer()->helper->setup->timezoneSelect(
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
                {{ $this->container->html->select->genericList(
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

        {{--behind_load_balancer--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[behind_load_balancer]" id="behind_load_balancer"
                            {{ $config->get('behind_load_balancer', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="behind_load_balancer">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_BEHIND_LOAD_BALANCER')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_BEHIND_LOAD_BALANCER_HELP')
                </div>
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

        {{--stats_collection--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[stats_collection]" id="stats_collection"
                            {{ $config->get('stats_collection', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="stats_collection">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_STATS_COLLECTION')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_STATS_COLLECTION_HELP')
                    <a href="@route('index.php?view=usagestats')"
                       target="_blank">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_STATS_COLLECTION_HELP_LEARN_MORE')
                        <span class="fa fa-fw fa-arrow-turn-right" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>