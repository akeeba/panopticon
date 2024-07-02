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
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_DATABASE')</h3>
    <div class="card-body">
        <div class="alert alert-info">
            <h4 class="alert-heading h6">
                <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                @lang('PANOPTICON_SYSCONFIG_LBL_DATABASE_WARNING_HEAD')
            </h4>
            <p>
                @lang('PANOPTICON_SYSCONFIG_LBL_DATABASE_WARNING_BODY')
            </p>
        </div>

        {{-- dbdriver --}}
        <div class="row mb-3">
            <label for="dbdriver" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBDRIVER')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'mysqli' => 'PANOPTICON_SYSCONFIG_OPT_DBDRIVER_MYSQLI',
                        'pdomysql' => 'PANOPTICON_SYSCONFIG_OPT_DBDRIVER_PDOMYSQL',
                    ],
                    name: 'options[dbdriver]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('dbdriver', 'mysqli'),
                    idTag: 'dbdriver',
                    translate: true
                ) }}
            </div>
        </div>

        {{--dbhost--}}
        <div class="row mb-3">
            <label for="dbhost" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBHOST')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbhost" name="options[dbhost]"
                       value="{{{ $config->get('dbhost', 'localhost') }}}"
                       required
                >
            </div>
        </div>

        {{--dbuser--}}
        <div class="row mb-3">
            <label for="dbuser" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBUSER')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbuser" name="options[dbuser]"
                       value="{{{ $config->get('dbuser', '') }}}"
                       required
                >
            </div>
        </div>

        {{--dbpass--}}
        <div class="row mb-3">
            <label for="dbpass" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBPASS')
            </label>
            <div class="col-sm-9">
                <input type="password" class="form-control" id="dbpass" name="options[dbpass]"
                       value="{{{ $config->get('dbpass', '') }}}"
                       required
                >
            </div>
        </div>

        {{--dbname--}}
        <div class="row mb-3">
            <label for="dbname" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBNAME')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbname" name="options[dbname]"
                       value="{{{ $config->get('dbname', '') }}}"
                       required
                >
            </div>
        </div>

        {{--prefix--}}
        <div class="row mb-3">
            <label for="prefix" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBPREFIX')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="prefix" name="options[prefix]"
                       value="{{{ $config->get('prefix', '') }}}"
                       required
                >
            </div>
        </div>

        {{--dbcharset--}}
        <div class="row mb-3">
            <label for="dbcharset" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBCHARSET')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'utf8mb4' => 'utf8mb4',
                        'utf8' => 'utf8',
                    ],
                    name: 'options[dbcharset]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('dbcharset', 'utf8mb4_unicode_ci'),
                    idTag: 'dbcharset',
                    translate: false
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBCHARSET_HELP')
                </div>
            </div>
        </div>

        {{--dbencryption--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[dbencryption]" id="dbencryption" value="1"
                            {{ $config->get('dbencryption', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="dbencryption">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBENCRYPTION')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBENCRYPTION_HELP')
                </div>
            </div>
        </div>

        {{--dbsslca--}}
        <div class="row mb-3" data-showon='[{"field":"options[dbencryption]","values":["1"],"sign":"=","op":""}]'>
            <label for="dbsslca" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBSSLCA')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbsslca" name="options[dbsslca]"
                       value="{{{ $config->get('dbsslca', '') }}}"
                >
            </div>
        </div>

        {{--dbsslkey--}}
        <div class="row mb-3" data-showon='[{"field":"options[dbencryption]","values":["1"],"sign":"=","op":""}]'>
            <label for="dbsslkey" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBSSLKEY')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbsslkey" name="options[dbsslkey]"
                       value="{{{ $config->get('dbsslkey', '') }}}"
                >
            </div>
        </div>

        {{--dbsslcert--}}
        <div class="row mb-3" data-showon='[{"field":"options[dbencryption]","values":["1"],"sign":"=","op":""}]'>
            <label for="dbsslcert" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_dbsslcert')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="dbsslcert" name="options[dbsslcert]"
                       value="{{{ $config->get('dbsslcert', '') }}}"
                >
            </div>
        </div>

        {{--dbsslverifyservercert--}}
        <div class="row mb-3" data-showon='[{"field":"options[dbencryption]","values":["1"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[dbsslverifyservercert]" id="dbsslverifyservercert"
                           {{ $config->get('dbsslverifyservercert', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="dbsslverifyservercert">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_DBSSLVERIFYSERVERCERT')
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>