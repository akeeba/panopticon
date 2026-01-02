<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\Library\View\FakeView $this
 * @var \Akeeba\Panopticon\Model\Site            $site
 */

?>

<div class="card">
    <h3 class="card-header">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_TITLE')
    </h3>

    <div class="card-body">
        <div class="alert alert-info">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_NOTE')
        </div>

        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" value="1"
                           name="config[uptime.enable]" id="config_uptime_enable"
                            {{ $site->getConfig()->get('uptime.enable', 1) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="config_uptime_enable">
                        @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_ENABLED')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_ENABLED_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="config_uptime_path" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_PATH')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <div class="input-group-text">
                        {{{ rtrim($site->getBaseUrl(), '/') }}}/
                    </div>
                    <input type="text" class="form-control"
                           name="config[uptime.path]" id="config_uptime_path"
                           value="{{ $site->getConfig()->get('uptime.path', '') }}"
                    >
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <label for="config_uptime_string" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_STRING')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control"
                       name="config[uptime.string]" id="config_uptime_string"
                       value="{{ $site->getConfig()->get('uptime.string', '') }}"
                >
                <div class="form-text">@lang('PANOPTICON_SITES_FIELD_CONFIG_UPTIME_STRING_HELP')</div>
            </div>
        </div>
    </div>
</div>