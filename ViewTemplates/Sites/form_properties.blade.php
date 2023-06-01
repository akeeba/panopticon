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
$isAdmin = $this->container->userManager->getUser()->authorise('panopticon.admin', $this->item);

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-choice').forEach((element) => {
        new Choices(element, {allowHTML: false, removeItemButton: true, placeholder: true, placeholderValue: ""});
    });
});

JS;

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

{{-- Groups--}}
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
            attribs: [
                'class' => 'form-select js-choice',
                'multiple' => 'multiple',
            ],
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
        @if($isAdmin)
            {{ Setup::userSelect($this->item->created_by, 'created_by', attribs: ['class' => 'form-select js-choice']) }}
        @else
            @unless(($this->item->created_by ?? 0) <= 0)
					<?php $userCreator = $this->container->userManager->getUser($this->item->created_by ?? 0) ?>
                <span class="fw-medium">{{ $userCreator->getName() }}</span>
                (<span class="font-monospace text-muted">{{ $userCreator->getUsername() }}</span>)
            @endunless

        @endif
    </div>
</div>

{{-- created_on --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_CREATED_ON')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            @if($isAdmin)
                <input type="datetime-local" name="created_on" id="created_on"
                       class="form-control"
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                       value="{{ Awf\Html\Html::date($this->item->created_on, 'Y-m-d\TH:i:s', 'UTC', $this->container->application) }}"
                >
                <span class="input-group-text">GMT</span>
            @else
                {{ Awf\Html\Html::date($this->item->created_on, \Awf\Text\Text::_('DATE_FORMAT_LC7'), 'UTC', $this->container->application) }}
            @endif
        </div>
    </div>
</div>

{{-- modified_by --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_MODIFIED_BY')
    </label>
    <div class="col-sm-9">
        @if($isAdmin)
            {{ Setup::userSelect($this->item->modified_by, 'modified_by', attribs: ['class' => 'form-select js-choice']) }}
        @else
            @unless(($this->item->created_by ?? 0) <= 0)
					<?php $userModifier = $this->container->userManager->getUser($this->item->modified_by ?? 0) ?>
                <span class="fw-medium">{{ $userModifier->getName() }}</span>
                (<span class="font-monospace text-muted">{{ $userModifier->getUsername() }}</span>)
            @endunless
        @endif
    </div>
</div>

{{-- modified_on --}}
<div class="row mb-3">
    <label for="name" class="col-sm-3 col-form-label">
        @lang('PANOPTICON_LBL_FIELD_MODIFIED_ON')
    </label>
    <div class="col-sm-9">
        <div class="input-group">
            @if($isAdmin)
                <input type="datetime-local" name="modified_on" id="modified_on"
                       class="form-control"
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                       value="{{ Awf\Html\Html::date($this->item->modified_on, 'Y-m-d\TH:i:s', 'UTC', $this->container->application) }}"
                >
                <span class="input-group-text">GMT</span>
            @else
                {{ Awf\Html\Html::date($this->item->created_on, \Awf\Text\Text::_('DATE_FORMAT_LC7'), 'UTC', $this->container->application) }}
            @endif
        </div>
    </div>
</div>
