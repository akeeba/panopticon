<?php
/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <div class="card-body">
        <h3 class="card-title h5">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DISPLAY')</h3>

        {{--darkmode--}}
        <div class="row mb-3">
            <label for="darkmode" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DARKMODE')
            </label>
            <div class="col-sm-9">
                {{ \Awf\Html\Select::genericList(
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

    </div>
</div>