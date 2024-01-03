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
{{-- Caching --}}
{{--================================================================================================================--}}

<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_CACHING')</h3>
    <div class="card-body">
        {{--caching_time--}}
        <div class="row mb-3">
            <label for="caching_time" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CACHING_TIME')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="caching_time" name="options[caching_time]"
                           value="{{{ $config->get('caching_time', 60) }}}"
                           min="1" max="527040" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_MINUTES')
                    </div>
                </div>
            </div>
        </div>

        {{--cache_adapter--}}
        <div class="row mb-3">
            <label for="cache_adapter" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CACHE_ADAPTER')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'filesystem' => 'PANOPTICON_SYSCONFIG_OPT_CACHE_ADAPTER_FILESYSTEM',
                        'linuxfs' => 'PANOPTICON_SYSCONFIG_OPT_CACHE_ADAPTER_LINUXFS',
                        'db' => 'PANOPTICON_SYSCONFIG_OPT_CACHE_ADAPTER_DB',
                        'memcached' => 'PANOPTICON_SYSCONFIG_OPT_CACHE_ADAPTER_MEMCACHED',
                        'redis' => 'PANOPTICON_SYSCONFIG_OPT_CACHE_ADAPTER_REDIS',
                    ],
                    name: 'options[cache_adapter]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('cache_adapter', 'filesystem'),
                    idTag: 'cache_adapter',
                    translate: true
                ) }}
            </div>
        </div>

        {{--caching_redis_dsn--}}
        <div class="row mb-3" data-showon='[{"field":"options[cache_adapter]","values":["redis"],"sign":"=","op":""}]'>
            <label for="caching_redis_dsn" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CACHING_REDIS_DSN')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="caching_redis_dsn" name="options[caching_redis_dsn]"
                       value="{{{ $config->get('caching_redis_dsn', '') }}}"
                >
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CACHING_REDIS_DSN_HELP')
                </div>
            </div>
        </div>

        {{--caching_memcached_dsn--}}
        <div class="row mb-3" data-showon='[{"field":"options[cache_adapter]","values":["memcached"],"sign":"=","op":""}]'>
            <label for="caching_memcached_dsn" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CACHING_MEMCACHED_DSN')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="caching_memcached_dsn" name="options[caching_memcached_dsn]"
                       value="{{{ $config->get('caching_memcached_dsn', '') }}}"
                >
            </div>
        </div>

    </div>
</div>