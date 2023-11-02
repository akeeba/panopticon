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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DBTOOLS')</h3>
    <div class="card-body">

        {{-- dbbackup_auto --}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[dbbackup_auto]" id="dbbackup_auto"
                           {{ $config->get('dbbackup_auto', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="dbbackup_auto">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_AUTO')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_AUTO_HELP')
                </div>
            </div>
        </div>

        {{-- dbbackup_compress --}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[dbbackup_compress]" id="dbbackup_compress"
                           {{ $config->get('dbbackup_compress', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="dbbackup_compress">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_COMPRESS')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_COMPRESS_HELP')
                </div>
            </div>
        </div>

        {{-- dbbackup_maxfiles --}}
        <div class="row mb-3">
            <label for="dbbackup_maxfiles" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_MAXFILES')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="dbbackup_maxfiles" name="options[dbbackup_maxfiles]"
                       value="{{{ $config->get('dbbackup_maxfiles', 15) }}}"
                       min="1"
                       max="730"
                       step="1"
                       required
                >
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBBACKUP_MAXFILES_HELP')
                </div>
            </div>
        </div>

    </div>
</div>