<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config     = $this->item?->getConfig() ?? new Awf\Registry\Registry();

?>

<div class="row mt-3 mb-4">
    <label for="config_ssl_warning" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_SSL_WARNING')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            <input type="text" class="form-control"
                   name="config[config.ssl.warning]" id="config_ssl_warning"
                   value="{{ $config->get('config.ssl.warning', 7) }}"
            >
            <div class="input-group-text">
                @lang('PANOPTICON_SYSCONFIG_LBL_UOM_DAYS')
            </div>
        </div>
        <div class="form-text">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_SSL_WARNING_HELP')
        </div>
    </div>
</div>

<div class="row mt-3 mb-4">
    <label for="config_backup_max_age" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_BACKUP_MAX_AGE')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            <input type="text" class="form-control"
                   name="config[config.backup.max_age]" id="config_backup_max_age"
                   value="{{ $config->get('config.backup.max_age', 168) }}"
            >
            <div class="input-group-text">
                @lang('PANOPTICON_SYSCONFIG_LBL_UOM_HOURS')
            </div>
        </div>
        <div class="form-text">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_BACKUP_MAX_AGE_HELP')
        </div>
    </div>
</div>
