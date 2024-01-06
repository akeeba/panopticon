<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
{{--================================================================================================================--}}
{{-- Uptime Monitoring --}}
{{--================================================================================================================--}}

<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_UPTIME')</h3>
    <div class="card-body">
        <div class="row mb-3">
            <label for="uptime" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_UPTIME')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: $this->getModel()->getUptimeOptions(),
                    name: 'options[uptime]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('uptime', 'none'),
                    idTag: 'uptime',
                    translate: true
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_UPTIME_HELP')
                </div>
            </div>
        </div>
    </div>
</div>

