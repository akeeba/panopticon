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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DISPLAY')</h3>
    <div class="card-body">

        {{-- template --}}
        <div class="row mb-3">
            <label for="optionstemplate" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TEMPLATE')
            </label>
            <div class="col-sm-9">
                {{ $this->container->helper->setup->template(
                    selected: $config->get('template', 'template'),
                    name: 'options[template]',
                    attribs: [
						'class' => 'form-control'
                    ]
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TEMPLATE_HELP')
                </div>
            </div>
        </div>

        {{-- theme --}}
        <div class="row mb-3">
            <label for="theme" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_THEME')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <div class="input-group-text">
                        media/css/
                    </div>

                    {{ $this->container->helper->setup->cssThemeSelect($config->get('theme', 'theme') ?: 'theme') }}

                    <div class="input-group-text">
                        .min.css
                    </div>
                </div>
            </div>
        </div>

        {{--darkmode--}}
        <div class="row mb-3">
            <label for="darkmode" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DARKMODE')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        1 => 'PANOPTICON_SYSCONFIG_OPT_DARKMODE_BROWSER',
                        2 => 'PANOPTICON_SYSCONFIG_OPT_DARKMODE_LIGHT',
                        3 => 'PANOPTICON_SYSCONFIG_OPT_DARKMODE_DARK',
                    ],
                    name: 'options[darkmode]',
                    attribs: [
                        'class' => 'form-select'
                    ],
                    selected: $config->get('darkmode', 1),
                    idTag: 'darkmode',
                    translate: true
                ) }}
            </div>
        </div>

        {{--fontsize--}}
        <div class="row mb-3">
            <label for="fontsize" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FONTSIZE')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="fontsize" name="options[fontsize]"
                           value="{{{ $config->get('fontsize', 12) }}}"
                           min="8" max="48"
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_POINTS')
                    </div>
                </div>
            </div>
        </div>

        {{--phpwarnings--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[phpwarnings]" id="phpwarnings"
                            {{ $config->get('phpwarnings', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="phpwarnings">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_PHPWARNINGS')
                    </label>
                </div>
            </div>
        </div>

        {{--avatars--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[avatars]" id="avatars"
                            {{ $config->get('avatars', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="avatars">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_AVATARS')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_AVATARS_HELP')
                </div>
            </div>
        </div>

    </div>
</div>

<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DASHBOARD')</h3>
    <div class="card-body">
        {{--dashboard_reload_timer--}}
        <div class="row mb-3">
            <label for="dashboard_reload_timer" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DASHBOARD_RELOAD_TIMER')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="dashboard_reload_timer"
                           name="options[dashboard_reload_timer]"
                           value="{{{ $config->get('dashboard_reload_timer', 90) }}}"
                           min="0" max="86400"
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_SECONDS')
                    </div>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DASHBOARD_RELOAD_TIMER_HELP')
                </div>
            </div>
        </div>

        {{--dashboard_max_items--}}
        <div class="row mb-3">
            <label for="dashboard_max_items" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DASHBOARD_MAX_ITEMS')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="dashboard_max_items"
                           name="options[dashboard_max_items]"
                           value="{{{ $config->get('dashboard_max_items', 1000) }}}"
                           min="50" max="100000"
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_ITEMS')
                    </div>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DASHBOARD_MAX_ITEMS_HELP')
                </div>
            </div>
        </div>
    </div>
</div>