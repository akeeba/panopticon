<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');

?>
<div class="row mb-3">
    <label for="config_core_update_install" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_INSTALL')
    </label>
    <div class="col-sm-9">
        {{ \Awf\Html\Select::genericList(
                    data: [
                        '' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_GLOBAL',
                        'none' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE',
                        'email' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL',
                        'patch' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH',
                        'minor' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR',
                        'major' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR',
                    ],
                    name: 'config[config.core_update.install]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('config.core_update.install', ''),
                    idTag: 'config_core_update_install',
                    translate: true
                ) }}
        <div class="form-text">
            @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_COREUPDATE_INSTALL_HELP')
        </div>
    </div>
</div>

<div class="row mb-3" {{ $this->showOn('config[config.core_update.install]!:none[AND]config[config.core_update.install]!:email') }}>
    <label for="config_core_update_when" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_WHEN')
    </label>
    <div class="col-sm-9">
        {{ \Awf\Html\Select::genericList(
                    data: [
                        'immediately' => 'PANOPTICON_SITES_OPT_CONFIG_CORE_UPDATE_WHEN_IMMEDIATELY',
                        'time' => 'PANOPTICON_SITES_OPT_CONFIG_CORE_UPDATE_WHEN_TIME',
                    ],
                    name: 'config[config.core_update.when]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('config.core_update.when', 'immediately'),
                    idTag: 'config_core_update_when',
                    translate: true
                ) }}
    </div>
</div>

<div class="row mb-3" {{ $this->showOn('config[config.core_update.install]!:none[AND]config[config.core_update.install]!:email[AND]config[config.core_update.when]:time') }}>
    <label for="config_core_update_time_hour" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_TIME')
    </label>
    <div class="col-sm-9">
        <div class="d-flex flex-row gap-2 align-items-center">
            <input name="config[config.core_update.time.hour]" id="config_core_update_time_hour"
                   class="form-control"
                   value="{{ $config->get('config.core_update.time.hour', '0') }}">
            <strong>:</strong>
            <input name="config[config.core_update.time.minute]" id="config_core_update_time_minute"
                   class="form-control"
                   value="{{ $config->get('config.core_update.time.minute', '0') }}">
        </div>
        <div class="form-text">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_TIME_HELP')
        </div>
    </div>
</div>

<div class="row mb-3">
    <label for="config_core_update_email_cc" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_EMAIL_CC')
    </label>
    <div class="col-sm-9">
        <input name="config[config.core_update.email.cc]" id="config_core_update_email_cc"
               class="form-control" type="text" inputmode="email"
               value="{{ $config->get('config.core_update.email.cc', '') }}" >
        <div class="form-text">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_EMAIL_CC_HELP')
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" value="1"
                   name="config[config.core_update.email_error]" id="config_core_update_email_error"
                    {{ $config->get('config.core_update.email_error', true) ? 'checked' : '' }}
            >
            <label class="form-check-label" for="config_core_update_email_error">
                @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_EMAIL_ERROR')
            </label>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" value="1"
                   name="config[config.core_update.email_after]" id="config_core_update_email_after"
                    {{ $config->get('config.core_update.email_after', true) ? 'checked' : '' }}
            >
            <label class="form-check-label" for="config_core_update_email_after">
                @lang('PANOPTICON_SITES_FIELD_CONFIG_CORE_UPDATE_EMAIL_AFTER')
            </label>
        </div>
    </div>
</div>