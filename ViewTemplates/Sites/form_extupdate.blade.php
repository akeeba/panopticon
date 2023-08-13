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

use Awf\Text\Text;

?>
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
    <?php
        $effectivePreference = $item->preference ?: $this->globalExtUpdatePreferences[$key]?->preference;
		$effectivePreference = $effectivePreference ?: $this->defaultExtUpdatePreference;
        $effectivePreferenceText = Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_' . $effectivePreference);

		$globalPreference = $this->globalExtUpdatePreferences[$key]?->preference ?: $this->defaultExtUpdatePreference;
        $globalPreferenceText = Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_' . $globalPreference);
    ?>
    <tr>
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
            {{{ $item->name }}}
            <div class="small text-muted font-monospace">{{{ ltrim($key, 'a') }}}</div>
        </td>
        <td class="d-none d-lg-table-cell">
            <div class="small">
                @if ($item->authorUrl)
                    <a href="{{{ $item->authorUrl }}}" target="_blank">
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
            {{ \Awf\Html\Select::genericList(
                data: [
                    ''      => Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_GLOBAL_ALT') .
                        ' ' . $globalPreferenceText
                    ,
                    'none'  => Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE'),
                    'email' => 'PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL',
                    'patch' => Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH'),
                    'minor' => Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR'),
                    'major' => Text::_('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR'),
                ],
                name: 'extupdates['.$key.']',
                attribs: $attribs,
                selected: $item->preference ?? '',
                idTag: 'extupdates_' . $key,
                translate: false
            ) }}
        </td>
    </tr>
    @endforeach
    </tbody>
</table>
@endif
