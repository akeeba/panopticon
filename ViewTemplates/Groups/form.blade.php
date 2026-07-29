<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Groups\Html $this
 * @var \Akeeba\Panopticon\Model\Groups     $model
 */
$model      = $this->getModel();
$privileges = $model->getPrivileges();
$token      = $this->container->session->getCsrfToken()->getValue();

$colourHelper  = $this->container->helper->colour;
$currentColour = $colourHelper->sanitise($model->colour ?? null);
$isPaletteColour = $currentColour !== null
	&& array_key_exists($currentColour, \Akeeba\Panopticon\Helper\Colour::PALETTE);
$customColour = ($currentColour !== null && !$isPaletteColour) ? $currentColour : null;

// The preview badge would collapse to nothing on a new, untitled group (.badge:empty { display: none })
$previewTitle = trim((string) ($model->title ?? '')) ?: $this->getLanguage()
	->text('PANOPTICON_GROUPS_FIELD_COLOUR_PREVIEW_PLACEHOLDER');

$mcpToolNames = array_map(
	fn($tool) => $tool->getName(),
	(new \Akeeba\Panopticon\Library\Mcp\ToolRegistry($this->container))->getAllTools()
);
sort($mcpToolNames);
$mcpToolOptions     = array_combine($mcpToolNames, $mcpToolNames);
$mcpDisallowedTools = $model->getMcpDisallowedTools();

?>
<form action="@route('index.php?view=groups')" method="post" name="adminForm" id="adminForm">
    <div class="row mb-3">
        <label for="title" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_GROUPS_FIELD_TITLE')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="title" id="title"
                   value="{{{ $model->title ?? '' }}}" required
            >
        </div>
    </div>

    <div class="row mb-3">
        <fieldset class="d-flex" id="group-colour-picker">
            <legend class="col-sm-3 col-form-label">
                @lang('PANOPTICON_GROUPS_FIELD_COLOUR')
            </legend>
            <div class="col-sm-9">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" role="radiogroup"
                     aria-label="@lang('PANOPTICON_GROUPS_FIELD_COLOUR')"
                >
                    <input type="radio" class="btn-check js-group-colour-none" name="colour" value=""
                           id="colour_none" autocomplete="off"
                           {{ $currentColour === null ? 'checked' : '' }}
                    >
                    <label class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2"
                           for="colour_none" title="@lang('PANOPTICON_GROUPS_FIELD_COLOUR_NONE')"
                    >
                        <span class="fa fa-fw fa-ban" aria-hidden="true"></span>
                        <span>@lang('PANOPTICON_GROUPS_FIELD_COLOUR_NONE')</span>
                    </label>
                    @foreach (\Akeeba\Panopticon\Helper\Colour::PALETTE as $paletteHex => $paletteLangKey)
                        <input type="radio" class="btn-check js-group-colour-swatch" name="colour"
                               value="{{{ $paletteHex }}}" id="colour_{{{ substr($paletteHex, 1) }}}"
                               autocomplete="off"
                               {{ $currentColour === $paletteHex ? 'checked' : '' }}
                        >
                        <label class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2"
                               for="colour_{{{ substr($paletteHex, 1) }}}" title="@lang($paletteLangKey)"
                        >
                            {{-- Not a .badge: Bootstrap hides empty badges (.badge:empty { display: none }) --}}
                            <span class="d-inline-block rounded-circle border border-1 border-dark-subtle flex-shrink-0"
                                  style="width: 1rem; height: 1rem; background-color: {{{ $paletteHex }}}"
                                  aria-hidden="true"
                            ></span>
                            <span>@lang($paletteLangKey)</span>
                        </label>
                    @endforeach
                    <input type="radio" class="btn-check js-group-colour-custom" name="colour"
                           value="{{{ $customColour ?? '' }}}" id="colour_custom" autocomplete="off"
                           {{ $customColour !== null ? 'checked' : '' }}
                    >
                    <label class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2"
                           for="colour_custom" title="@lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')"
                    >
                        <span class="fa fa-fw fa-magnifying-glass" aria-hidden="true"></span>
                        <span>@lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')</span>
                    </label>
                </div>

                <div class="js-group-colour-custom-row {{ $customColour === null ? 'd-none' : '' }} mb-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <label for="colour_custom_picker" class="form-label mb-0">
                            @lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')
                        </label>
                        <input type="color" class="form-control form-control-color js-group-colour-custom-picker"
                               id="colour_custom_picker" value="{{{ $customColour ?? '#000000' }}}"
                               title="@lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')"
                        >
                        <input type="text" class="form-control js-group-colour-custom-hex" id="colour_custom_hex"
                               value="{{{ $customColour ?? '' }}}" placeholder="#rrggbb" maxlength="7"
                               style="max-width: 8rem"
                        >
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <div class="js-group-colour-preview-light border rounded p-2"
                         style="background-color: #f8f9fa"
                    >
                        @include('Common/groupbadge', ['title' => $previewTitle, 'colour' => $currentColour])
                    </div>
                    <div class="js-group-colour-preview-dark border rounded p-2"
                         style="background-color: #212529"
                    >
                        @include('Common/groupbadge', ['title' => $previewTitle, 'colour' => $currentColour])
                    </div>
                </div>

                <div class="form-text">@lang('PANOPTICON_GROUPS_FIELD_COLOUR_HELP')</div>
            </div>
        </fieldset>
    </div>

    <div class="row mb-3">
        <fieldset class="d-flex">
            <legend class="col-sm-3 col-form-label">
                @lang('PANOPTICON_GROUPS_FIELD_PERMISSIONS')
            </legend>
            <div class="col-sm-9" id="permissions">
                <div class="w-100 d-flex flex-column gap-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                            name="permissions[panopticon.view]"
                            {{ in_array('panopticon.view', $privileges) ? 'checked' : '' }}
                            id="permissions_view">
                        <label class="form-check-label" for="permissions_view">@lang('PANOPTICON_PRIVILEGE_VIEW')</label>
                        <div class="form-text">@lang('PANOPTICON_PRIVILEGE_VIEW_HELP')</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                            name="permissions[panopticon.run]"
                            {{ in_array('panopticon.run', $privileges) ? 'checked' : '' }}
                            id="permissions_run">
                        <label class="form-check-label" for="permissions_run">@lang('PANOPTICON_PRIVILEGE_RUN')</label>
                        <div class="form-text">@lang('PANOPTICON_PRIVILEGE_RUN_HELP')</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                            name="permissions[panopticon.admin]"
                            {{ in_array('panopticon.admin', $privileges) ? 'checked' : '' }}
                            id="permissions_admin">
                        <label class="form-check-label" for="permissions_admin">@lang('PANOPTICON_PRIVILEGE_ADMIN')</label>
                        <div class="form-text">@lang('PANOPTICON_PRIVILEGE_ADMIN_HELP')</div>
                    </div>

                </div>
            </div>
        </fieldset>
    </div>

    <div class="row mb-3">
        <label for="api_token_limit" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_GROUPS_FIELD_API_TOKEN_LIMIT')
        </label>
        <div class="col-sm-9">
            <input type="number" class="form-control" id="api_token_limit" name="api_token_limit"
                   min="0" step="1"
                   value="{{ $model->getApiTokenLimit() !== null ? $model->getApiTokenLimit() : '' }}"
                   placeholder="@lang('PANOPTICON_GROUPS_FIELD_API_TOKEN_LIMIT_PLACEHOLDER')">
            <div class="form-text">@lang('PANOPTICON_GROUPS_FIELD_API_TOKEN_LIMIT_HELP')</div>
        </div>
    </div>

    <div class="row mb-3">
        <label for="mcp_disallowed_tools" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_GROUPS_FIELD_MCP_DISALLOWED_TOOLS')
        </label>
        <div class="col-sm-9">
            {{ $this->container->html->select->genericList(
                data: $mcpToolOptions,
                name: 'mcp_disallowed_tools[]',
                attribs: [
                    'class' => 'form-select js-choice',
                    'multiple' => 'multiple',
                ],
                selected: $mcpDisallowedTools
            ) }}
            <div class="form-text">@lang('PANOPTICON_GROUPS_FIELD_MCP_DISALLOWED_TOOLS_HELP')</div>
        </div>
    </div>

    <input type="hidden" name="id" value="{{ (int) $model->id ?? 0 }}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="browse">
</form>