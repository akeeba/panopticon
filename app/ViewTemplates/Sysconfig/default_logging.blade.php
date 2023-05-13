<?php
/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
{{--================================================================================================================--}}
{{-- Logging --}}
{{--================================================================================================================--}}

<div class="card">
    <div class="card-body">
        <h3 class="card-title h5">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_LOGGING')</h3>

        {{--log_level--}}
        <div class="row mb-3">
            <label for="log_level" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOG_LEVEL')
            </label>
            <div class="col-sm-9">
                {{ \Awf\Html\Select::genericList(
                    data: [
                        'error' => 'PANOPTICON_SYSCONFIG_OPT_LOG_LEVEL_ERROR',
                        'warning' => 'PANOPTICON_SYSCONFIG_OPT_LOG_LEVEL_WARNING',
                        'notice' => 'PANOPTICON_SYSCONFIG_OPT_LOG_LEVEL_NOTICE',
                        'info' => 'PANOPTICON_SYSCONFIG_OPT_LOG_LEVEL_INFO',
                        'debug' => 'PANOPTICON_SYSCONFIG_OPT_LOG_LEVEL_DEBUG',
                    ],
                    name: 'options[log_level]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('log_level', 'warning'),
                    idTag: 'log_level',
                    translate: true
                ) }}
            </div>
        </div>

        {{--log_rotate_files--}}
        <div class="row mb-3">
            <label for="log_rotate_files" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOG_ROTATE_FILES')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="log_rotate_files" name="options[log_rotate_files]"
                           value="{{{ $config->get('log_rotate_files', 3) }}}"
                           min="0" max="100" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_FILES')
                    </div>
                </div>
            </div>
        </div>

        {{--log_backup_threshold--}}
        <div class="row mb-3">
            <label for="log_backup_threshold" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOG_BACKUP_THRESHOLD')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="log_backup_threshold" name="options[log_backup_threshold]"
                           value="{{{ $config->get('log_backup_threshold', 14) }}}"
                           min="0" max="65535" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_DAYS')
                    </div>
                </div>
            </div>
        </div>

        {{--log_rotate_compress--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="options[log_rotate_compress]" id="log_rotate_compress"
                            {{ $config->get('log_rotate_compress', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="log_rotate_compress">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_LOG_ROTATE_COMPRESS')
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>