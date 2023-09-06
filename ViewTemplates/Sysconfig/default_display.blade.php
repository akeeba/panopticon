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
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DISPLAY')</h3>
    <div class="card-body">

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
                    <input type="text" class="form-control" id="theme" name="options[theme]"
                           value="{{{ $config->get('theme', 'theme') ?: 'theme' }}}"
                    >
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

    </div>
</div>