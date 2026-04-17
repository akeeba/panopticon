<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config     = $this->item?->getConfig() ?? new Awf\Registry\Registry();
$updateTime = sprintf(
	'%02u:%02u',
	$config->get('config.extensions_update.time.hour', '0'),
	$config->get('config.extensions_update.time.minute', '0')
);

?>
<div class="row mb-3">
    <label for="config_extensions_update_when" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_FIELD_CONFIG_EXTENSIONS_UPDATE_WHEN')
    </label>
    <div class="col-sm-9">
        {{ $this->container->html->select->genericList(
                    data: [
                        'immediately' => 'PANOPTICON_SITES_OPT_CONFIG_EXTENSIONS_UPDATE_WHEN_IMMEDIATELY',
                        'time' => 'PANOPTICON_SITES_OPT_CONFIG_EXTENSIONS_UPDATE_WHEN_TIME',
                    ],
                    name: 'config[config.extensions_update.when]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('config.extensions_update.when', 'immediately'),
                    idTag: 'config_extensions_update_when',
                    translate: true
                ) }}
    </div>
</div>

<div class="row mb-3" {{ $this->showOn('config[config.extensions_update.when]:time') }}>
    <fieldset class="d-flex">
        <label class="col-sm-3 col-form-label" for="extensions_update_time">
            @lang('PANOPTICON_SITES_FIELD_CONFIG_EXTENSIONS_UPDATE_TIME')
        </label>
        <div class="col-sm-9 d-flex flex-row gap-2 align-items-center ps-2">
            <input type="time" name="extensions_update_time" id="extensions_update_time"
                   class="form-control"
                   pattern="[0-9]{2}:[0-9]{2}"
                   value="{{ $updateTime }}"
            >
        </div>
    </fieldset>
    <div class="form-text offset-sm-3 col-sm-9">
        @sprintf(
	        'PANOPTICON_SITES_FIELD_CONFIG_EXTENSIONS_UPDATE_TIME_HELP',
	        (new DateTimeZone($this->container->appConfig->get('timezone', 'UTC') ?: 'UTC'))->getName()
	    )
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
        $effectivePreferenceText = $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_' . $effectivePreference);

		$globalPreference = $this->globalExtUpdatePreferences[$key]?->preference ?: $this->defaultExtUpdatePreference;
        $globalPreferenceText = $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_' . $globalPreference);
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
            {{{ strip_tags($item->name) }}}
            <div class="small text-muted font-monospace">{{{ str_starts_with($key, 'atpl_') || str_starts_with($key, 'amod_') ? ltrim($key, 'a') : $key }}}</div>
        </td>
        <td class="d-none d-lg-table-cell">
            <div class="small">
                @if ($item->authorUrl)
                    <a href="{{ (str_starts_with($item->authorUrl, 'http://') || str_starts_with($item->authorUrl, 'https://') || str_starts_with($item->authorUrl, '//')) ? '' : '//' }}{{{ $item->authorUrl }}}" target="_blank">
                        {{{ strip_tags($item->author) }}}
                    </a>
                @else
                    {{{ strip_tags($item->author) }}}
                @endif
            </div>
            @if ($item->authorEmail)
                <div class="small text-muted">
                    {{{ strip_tags($item->authorEmail) }}}
                </div>
            @endif
        </td>
        <td>
            <label for="extupdates_{{ $key }}" class="visually-hidden">
                @sprintf('PANOPTICON_SYSCONFIG_LBL_EXTENSIONS_PREFERENCE_SELECT', strip_tags($item->name))
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
                    ''      => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_GLOBAL_ALT') .
                        ' ' . $globalPreferenceText
                    ,
                    'none'  => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_NONE'),
                    'email' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_EMAIL'),
                    'patch' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_PATCH'),
                    'minor' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MINOR'),
                    'major' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_TASKS_COREUPDATE_INSTALL_MAJOR'),
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
