<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_CHECKSUMS')</h3>
    <div class="card-body">
        {{--checksums_base_url--}}
        <div class="row mb-3">
            <label for="checksums_base_url" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CHECKSUMS_BASE_URL')
            </label>
            <div class="col-sm-9">
                <input type="url" class="form-control" id="checksums_base_url"
                       name="options[checksums_base_url]"
                       value="{{{ $config->get('checksums_base_url', '') }}}"
                       placeholder="https://getpanopticon.com/checksums">
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CHECKSUMS_BASE_URL_HELP')
                </div>
            </div>
        </div>

    </div>
</div>
