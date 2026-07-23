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
            <div class="col-sm-9 d-flex flex-wrap align-items-center gap-2">
                <label class="d-flex flex-column align-items-center gap-1" style="width: 3rem"
                       title="@lang('PANOPTICON_GROUPS_FIELD_COLOUR_NONE')"
                >
                    <input type="radio" class="visually-hidden js-group-colour-none" name="colour" value=""
                           {{ $currentColour === null ? 'checked' : '' }}
                    >
                    <span class="badge bg-secondary text-light rounded-circle p-3 border border-2"></span>
                    <span class="small text-center">@lang('PANOPTICON_GROUPS_FIELD_COLOUR_NONE')</span>
                </label>
                @foreach (\Akeeba\Panopticon\Helper\Colour::PALETTE as $paletteHex => $paletteLangKey)
                    <label class="d-flex flex-column align-items-center gap-1" style="width: 3rem"
                           title="@lang($paletteLangKey)"
                    >
                        <input type="radio" class="visually-hidden js-group-colour-swatch" name="colour"
                               value="{{{ $paletteHex }}}" aria-label="@lang($paletteLangKey)"
                               {{ $currentColour === $paletteHex ? 'checked' : '' }}
                        >
                        <span class="badge rounded-circle p-3 border border-2"
                              style="background-color: {{{ $paletteHex }}}"
                        ></span>
                        <span class="small text-center">@lang($paletteLangKey)</span>
                    </label>
                @endforeach

                <input type="radio" class="visually-hidden" name="colour" id="colour_custom_radio"
                       value="{{{ $customColour ?? '' }}}"
                       {{ $customColour !== null ? 'checked' : '' }}
                >

                <div class="d-flex flex-column gap-1 ms-2">
                    <label for="colour_custom_picker" class="form-label mb-0">
                        @lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')
                    </label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" class="form-control form-control-color js-group-colour-custom-picker"
                               id="colour_custom_picker" value="{{{ $customColour ?? '#000000' }}}"
                               title="@lang('PANOPTICON_GROUPS_FIELD_COLOUR_CUSTOM')"
                        >
                        <input type="text" class="form-control js-group-colour-custom-hex" id="colour_custom_hex"
                               value="{{{ $customColour ?? '' }}}" placeholder="#rrggbb" maxlength="7"
                               style="max-width: 8rem"
                        >
                        <span class="js-group-colour-preview">
                            @include('Common/groupbadge', ['title' => $model->title ?? '', 'colour' => $currentColour])
                        </span>
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