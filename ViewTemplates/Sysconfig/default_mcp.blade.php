<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Mcp\ToolRegistry;
use Awf\Uri\Uri;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */
$config = $this->container->appConfig;

$allTools = array_map(
	fn($tool) => $tool->getName(),
	(new ToolRegistry($this->container))->getAllTools()
);
sort($allTools);

$mcpUrl = rtrim(Uri::base(false, $this->container), '/') . '/mcp';
?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_MCP')</h3>
    <div class="card-body">
        <p class="text-body-secondary">
            @lang('PANOPTICON_SYSCONFIG_LBL_MCP_INTRO')
        </p>

        {{-- mcp_enabled --}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mcp_enabled]" id="mcp_enabled"
                           value="1" {{ $config->get('mcp_enabled', false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="mcp_enabled">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_ENABLED')
                    </label>
                </div>
                <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_ENABLED_HELP')</div>
            </div>
        </div>

        {{-- MCP endpoint URL (read-only helper) --}}
        <div class="row mb-3" data-showon='[{"field":"options[mcp_enabled]","values":["1"],"sign":"=","op":""}]'>
            <label for="mcp_endpoint" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_ENDPOINT')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="mcp_endpoint" readonly
                       value="{{{ $mcpUrl }}}" onclick="this.select()">
                <div class="form-text">@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_ENDPOINT_HELP')</div>
            </div>
        </div>

        {{-- mcp_disallowed_tools --}}
        <div class="row mb-3" data-showon='[{"field":"options[mcp_enabled]","values":["1"],"sign":"=","op":""}]'>
            <label for="mcp_disallowed_tools" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_DISALLOWED_TOOLS')
            </label>
            <div class="col-sm-9">
                <textarea class="form-control font-monospace" id="mcp_disallowed_tools"
                          name="options[mcp_disallowed_tools]" rows="2"
                          >{{{ $config->get('mcp_disallowed_tools', '') }}}</textarea>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_DISALLOWED_TOOLS_HELP')
                </div>
                <div class="form-text">
                    <strong>@lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MCP_AVAILABLE_TOOLS'):</strong>
                    <code>{{ implode('</code>, <code>', array_map('htmlspecialchars', $allTools)) }}</code>
                </div>
            </div>
        </div>
    </div>
</div>
