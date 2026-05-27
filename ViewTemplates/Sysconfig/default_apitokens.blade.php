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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_APITOKENS')</h3>
    <div class="card-body">
        <div class="row mb-3">
            <label for="api_tokens_per_user_max" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_API_TOKENS_PER_USER_MAX')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="api_tokens_per_user_max"
                       name="options[api_tokens_per_user_max]" min="0" step="1"
                       value="{{{ $config->get('api_tokens_per_user_max', 50) }}}">
                <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_API_TOKENS_PER_USER_MAX_HELP')</div>
            </div>
        </div>
    </div>
</div>
