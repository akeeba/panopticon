<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Helper\Setup;
use Awf\Html\Html as HtmlHelper;

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config  = new \Awf\Registry\Registry($this->item?->config ?? '{}');
$isSuper = $this->container->userManager->getUser()->getPrivilege('panopticon.admin');

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-choice').forEach((element) => {
        new Choices(element, {allowHTML: false, removeItemButton: true, placeholder: true, placeholderValue: ""});
    });
});

JS;

// This is an unwanted effect of how AWF parses the default value on timestamp columns.
if ($this->item->created_on === 'CURRENT_TIMESTAMP')
{
	$this->item->created_on = null;
}

?>
@js('choices/choices.min.js')
@inlinejs($js)

{{-- enabled --}}
<div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" value="1"
                   name="enabled" id="enabled"
                    {{ $this->item->enabled ? 'checked' : '' }}
            >
            <label class="form-check-label" for="config_core_update_email_error">
                @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
            </label>
            <div class="form-text">
                @lang('PANOPTICON_LBL_FIELD_ENABLED_HELP')
            </div>
        </div>
    </div>
</div>

<h4 class="border-bottom mb-4">@lang('PANOPTICON_LBL_SITE_OWNERSHIP')</h4>

@if (!$isSuper)
    <div class="alert alert-info">
        <span class="fa fa-info-circle" aria-hidden="true"></span>
        @lang('PANOPTICON_LBL_SITE_OWNERSHIP_NON_SUPER')
    </div>
@endif

<div class="alert alert-info">
    @lang('PANOPTICON_SITES_LBL_GROUPS_INFO')
</div>

{{-- Groups, editable only by superusers --}}
<?php
    $attribs = $isSuper ? [] : ['disabled' => 'disabled'];
?>
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_SITES_LBL_GROUPS')
    </label>
    <div class="col-sm-9">
        {{ \Awf\Html\Select::genericList(
            data: array_merge([(object) [
				'value' => '',
				'text' => \Awf\Text\Text::_('PANOPTICON_SITES_LBL_GROUPS_PLACEHOLDER')
            ]], $this->getModel()->getGroupsForSelect()),
            name: 'groups[]',
            attribs: array_merge($attribs, [
                'class' => 'form-select js-choice',
                'multiple' => 'multiple',
            ]),
            selected: $config->get('config.groups', [])
        ) }}
    </div>
</div>

{{-- created_by --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_CREATED_BY')
    </label>
    <div class="col-sm-9">
        {{ Setup::userSelect($this->item->created_by, 'created_by',
                attribs: array_merge($attribs, ['class' => 'form-select js-choice'])) }}
    </div>
</div>

{{-- created_on --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_CREATED_ON')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            <input type="datetime-local" name="created_on" id="created_on"
                   class="form-control"
                   pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                   value="{{ Awf\Html\Html::date($this->item->created_on ?? 'now', 'Y-m-d\TH:i:s', 'UTC', $this->container->application) }}"
                   {{ $isSuper ? '' : 'disabled="disabled"' }}
            >
            <span class="input-group-text">GMT</span>
        </div>
    </div>
</div>

{{-- modified_by --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_MODIFIED_BY')
    </label>
    <div class="col-sm-9">
        {{ Setup::userSelect($this->item->modified_by, 'modified_by', attribs: array_merge($attribs, ['class' => 'form-select js-choice'])) }}
    </div>
</div>

{{-- modified_on --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_MODIFIED_ON')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            <input type="datetime-local" name="modified_on" id="modified_on"
                   class="form-control"
                   pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                   value="{{ Awf\Html\Html::date($this->item->modified_on ?? 'now', 'Y-m-d\TH:i:s', 'UTC', $this->container->application) }}"
                    {{ $isSuper ? '' : 'disabled="disabled"' }}
            >
            <span class="input-group-text">GMT</span>
        </div>
    </div>
</div>
