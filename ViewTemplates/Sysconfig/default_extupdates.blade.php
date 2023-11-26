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
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_EXTUPDATES')</h3>
    <div class="card-body">

	{{--tasks_extupdate_install--}}
        <div class="row mb-3">
            <label for="tasks_extupdate_install" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_EXTUPDATE_INSTALL')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'none' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE',
                        'email' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL',
                        'patch' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH',
                        'minor' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR',
                        'major' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR',
                    ],
                    name: 'options[tasks_extupdate_install]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('tasks_extupdate_install', 'email'),
                    idTag: 'tasks_extupdate_install',
                    translate: true
                ) }}

                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_TASKS_EXTUPDATE_INSTALL_HELP')
                </div>
            </div>
        </div>

        @if (empty($this->extUpdatePreferences))
        <div class="alert alert-info">
            <h3 class="h5 alert-heading">
                @lang('PANOPTICON_SYSCONFIG_NO_EXTENSIONS_HEAD')
            </h3>
            <p>
                @lang('PANOPTICON_SYSCONFIG_NO_EXTENSIONS_BODY')
            </p>
        </div>
        @else
        <div class="mt-3 mb-2 card card-body px-5 py-3 d-flex flex-column align-items-center justify-content-center gap-2">
            <div class="input-group">
                <span class="input-group-text">
                    <label for="extensions-filter-search" class="visually-hidden">
                        @lang('PANOPTICON_LBL_FORM_FILTER_EXTENSIONS')
                    </label>
                    <span class="fa fa-fw fa-search" aria-hidden="true"></span>
                </span>
                <input type="search" name="extensions-filter-search" id="extensions-filter-search"
                       class="form-control form-control-lg"
                >
                <button type="button" class="btn btn-outline-secondary"
                        id="extensions-filter-search-button">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </button>
            </div>
        </div>

        <table class="table table-hover table-responsive-sm">
            <caption class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_TABLE_CAPTION')</caption>
            <thead class="table-dark">
            <tr>
                <th>
                    @lang('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_NAME')
                </th>
                <th class="d-none d-lg-table-cell">
                    @lang('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_AUTHOR')
                </th>
                <th style="min-width: max(30vw, 100px)">
                    @lang('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_PREFERENCE')
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach ($this->extUpdatePreferences as $key => $item)
            <tr class="extensions-filterable-row">
                <td>
                    <span class="text-body-tertiary pe-2">
                        @if ($item->type === 'component')
                            <span class="fa fa-puzzle-piece" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')</span>
                        @elseif ($item->type === 'file')
                            <span class="fa fa-file-alt" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')</span>
                        @elseif ($item->type === 'library')
                            <span class="fa fa-book" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')</span>
                        @elseif ($item->type === 'package')
                            <span class="fa fa-boxes-packing" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')</span>
                        @elseif ($item->type === 'plugin')
                            <span class="fa fa-plug" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')</span>
                        @elseif ($item->type === 'module')
                            <span class="fa fa-cube" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')</span>
                        @elseif ($item->type === 'template')
                            <span class="fa fa-paint-brush" aria-hidden="true" title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')</span>
                        @endif
                    </span>
                     <span class="extensions-filterable-name">
                         {{{ $item->name }}}
                     </span>
                    <div class="small text-muted font-monospace extensions-filterable-key">{{{ ltrim($key, 'a') }}}</div>
                </td>
                <td class="d-none d-lg-table-cell">
                    <div class="small extensions-filterable-author">
                        @if ($item->authorUrl)
                            <a href="{{ (str_starts_with($item->authorUrl, 'http://') || str_starts_with($item->authorUrl, 'https://') || str_starts_with($item->authorUrl, '//')) ? '' : '//' }}{{{ $item->authorUrl }}}" target="_blank">
                                {{{ $item->author }}}
                            </a>
                        @else
                            {{{ $item->author }}}
                        @endif
                    </div>
                    @if ($item->authorEmail)
                    <div class="small text-muted">
                        {{{ $item->authorEmail }}}
                    </div>
                    @endif
                </td>
                <td>
                    <label for="extupdates_{{ $key }}" class="visually-hidden">
                        @sprintf('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_PREFERENCE_SELECT', $item->name)
                    </label>
                    <?php
                        $attribs = [
                            'class' => 'form-select',
                            'required' => 'required',
                        ];

                        if ($key === 'pkg_panopticon')
                        {
                            $attribs['disabled'] = true;
                            $item->preference    = 'major';
                        }
                    ?>

                    {{ $this->container->html->select->genericList(
                        data: [
                            ''      => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_GLOBAL',
                            'none'  => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE',
							'email' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL',
                            'patch' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH',
                            'minor' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR',
                            'major' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR',
                        ],
                        name: 'extupdates['.$key.']',
                        attribs: $attribs,
                        selected: $item->preference ?? '',
                        idTag: 'extupdates_' . $key,
                        translate: true
                    ) }}
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>