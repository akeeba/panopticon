<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sysconfig\Html $this
 */

$config = $this->container->appConfig;

?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_REGISTRATION')</h3>
    <div class="card-body">
        {{-- user_registration --}}
        <div class="row mb-3">
            <label for="user_registration" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_USER_REGISTRATION')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'disabled' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_REGISTRATION_DISABLED'),
                        'admin'    => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_REGISTRATION_ADMIN'),
                        'self'     => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_REGISTRATION_SELF'),
                    ],
                    name: 'options[user_registration]',
                    attribs: [
                        'class' => 'form-select',
                        'id'    => 'user_registration',
                    ],
                    selected: $config->get('user_registration', 'disabled'),
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_USER_REGISTRATION_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_allowed_domains --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""}]'>
            <label for="user_registration_allowed_domains" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_ALLOWED_DOMAINS')
            </label>
            <div class="col-sm-9">
                <textarea class="form-control" id="user_registration_allowed_domains"
                          name="options[user_registration_allowed_domains]"
                          rows="3">{{{ $config->get('user_registration_allowed_domains', '') }}}</textarea>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_ALLOWED_DOMAINS_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_disallowed_domains --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""}]'>
            <label for="user_registration_disallowed_domains" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_DISALLOWED_DOMAINS')
            </label>
            <div class="col-sm-9">
                <textarea class="form-control" id="user_registration_disallowed_domains"
                          name="options[user_registration_disallowed_domains]"
                          rows="3">{{{ $config->get('user_registration_disallowed_domains', '') }}}</textarea>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_DISALLOWED_DOMAINS_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_default_group --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""}]'>
            <label for="user_registration_default_group" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_DEFAULT_GROUP')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: array_replace([0 => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_REGISTRATION_NO_GROUP')], $this->getModel()->getGroupsForSelect(includeEmpty: false)),
                    name: 'options[user_registration_default_group]',
                    attribs: [
                        'class' => 'form-select',
                        'id'    => 'user_registration_default_group',
                    ],
                    selected: $config->get('user_registration_default_group', 0),
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_DEFAULT_GROUP_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_block_usernames --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""}]'>
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox"
                           name="options[user_registration_block_usernames]"
                           id="user_registration_block_usernames" value="1"
                            {{ $config->get('user_registration_block_usernames', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="user_registration_block_usernames">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_BLOCK_USERNAMES')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_BLOCK_USERNAMES_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_custom_blocked_usernames --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""},{"field":"options[user_registration_block_usernames]","values":["1"],"sign":"=","op":"AND"}]'>
            <label for="user_registration_custom_blocked_usernames" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_CUSTOM_BLOCKED_USERNAMES')
            </label>
            <div class="col-sm-9">
                <textarea class="form-control" id="user_registration_custom_blocked_usernames"
                          name="options[user_registration_custom_blocked_usernames]"
                          rows="3">{{{ $config->get('user_registration_custom_blocked_usernames', '') }}}</textarea>
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_CUSTOM_BLOCKED_USERNAMES_HELP')
                </div>
            </div>
        </div>

        {{-- user_registration_activation_days --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["self"],"sign":"=","op":""}]'>
            <label for="user_registration_activation_days" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_ACTIVATION_DAYS')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="number" class="form-control" id="user_registration_activation_days"
                           name="options[user_registration_activation_days]"
                           value="{{{ $config->get('user_registration_activation_days', 7) }}}"
                           min="1" max="90" required
                    >
                    <div class="input-group-text">
                        @lang('PANOPTICON_SYSCONFIG_LBL_UOM_DAYS')
                    </div>
                </div>
            </div>
        </div>

        {{-- user_registration_activation_tries --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["self"],"sign":"=","op":""}]'>
            <label for="user_registration_activation_tries" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_REGISTRATION_ACTIVATION_TRIES')
            </label>
            <div class="col-sm-9">
                <input type="number" class="form-control" id="user_registration_activation_tries"
                       name="options[user_registration_activation_tries]"
                       value="{{{ $config->get('user_registration_activation_tries', 3) }}}"
                       min="1" max="100" required
                >
            </div>
        </div>

        {{-- captcha_provider --}}
        <div class="row mb-3" data-showon='[{"field":"options[user_registration]","values":["admin","self"],"sign":"=","op":""}]'>
            <label for="captcha_provider" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CAPTCHA_PROVIDER')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'altcha' => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_CAPTCHA_ALTCHA'),
                        'none'   => $this->getLanguage()->text('PANOPTICON_SYSCONFIG_OPT_CAPTCHA_NONE'),
                    ],
                    name: 'options[captcha_provider]',
                    attribs: [
                        'class' => 'form-select',
                        'id'    => 'captcha_provider',
                    ],
                    selected: $config->get('captcha_provider', 'altcha'),
                ) }}
                <div class="form-text">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_CAPTCHA_PROVIDER_HELP')
                </div>
            </div>
        </div>
    </div>
</div>
