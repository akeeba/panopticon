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
$user   = $this->container->userManager->getUser();
?>
<div class="card">
    <h3 class="card-header h4">@lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_EMAIL')</h3>
    <div class="card-body">
        {{--mail_online--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mail_online]" id="mail_online"
                            {{ $config->get('mail_online', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="mail_online">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MAIL_ONLINE')
                    </label>
                </div>
            </div>
        </div>

        {{--immediate_email--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[immediate_email]" id="immediate_email"
                            {{ $config->get('immediate_email', true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="immediate_email">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_IMMEDIATE_EMAIL')
                    </label>
                </div>
            </div>
        </div>

        {{--mail_inline_images--}}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="options[mail_inline_images]" id="mail_inline_images"
                            {{ $config->get('mail_inline_images', false) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="mail_inline_images">
                        @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MAIL_INLINE_IMAGES')
                    </label>
                </div>
            </div>
        </div>

        {{--mailer--}}
        <div class="row mb-3">
            <label for="mailer" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_mailer')
            </label>
            <div class="col-sm-9">
                {{ $this->container->html->select->genericList(
                    data: [
                        'mail' => 'PANOPTICON_SYSCONFIG_OPT_MAILER_MAIL',
                        'sendmail' => 'PANOPTICON_SYSCONFIG_OPT_MAILER_SENDMAIL',
                        'smtp' => 'PANOPTICON_SYSCONFIG_OPT_MAILER_SMTP',
                    ],
                    name: 'options[mailer]',
                    attribs: [
                        'class' => 'form-select',
                        'required' => 'required',
                    ],
                    selected: $config->get('mailer', 'mail'),
                    idTag: 'mailer',
                    translate: true
                ) }}
            </div>
        </div>

        {{--mailfrom--}}
        <div class="row mb-3">
            <label for="mailfrom" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_MAILFROM')
            </label>
            <div class="col-sm-9">
                <input type="email" class="form-control" id="mailfrom" name="options[mailfrom]"
                       value="{{{ $config->get('mailfrom', '') }}}"
                >
            </div>
        </div>

        {{--fromname--}}
        <div class="row mb-3">
            <label for="fromname" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_FROMNAME')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" id="fromname" name="options[fromname]"
                       value="{{{ $config->get('fromname', 'Panopticon') }}}"
                >
            </div>
        </div>

        <div id="smtpSetup" data-showon='[{"field":"options[mailer]","values":["smtp"],"sign":"=","op":""}]'>
            {{--smtphost--}}
            <div class="row mb-3">
                <label for="smtphost" class="col-sm-3 col-form-label">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SMTPHOST')
                </label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="smtphost" name="options[smtphost]"
                           value="{{{ $config->get('smtphost', 'localhost') }}}"
                    >
                </div>
            </div>

            {{--smtpport--}}
            <div class="row mb-3">
                <label for="smtpport" class="col-sm-3 col-form-label">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SMTPPORT')
                </label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="smtpport" name="options[smtpport]"
                           value="{{{ $config->get('smtpport', 25) }}}"
                           min="1" max="65535"
                    >
                </div>
            </div>

            {{--smtpsecure--}}
            <div class="row mb-3">
                <label for="smtpsecure" class="col-sm-3 col-form-label">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SMTPSECURE')
                </label>
                <div class="col-sm-9">
                    {{ $this->container->html->select->genericList(
                        data: [
                            'none' => 'PANOPTICON_SYSCONFIG_OPT_SMTPSECURE_NONE',
                            'ssl' => 'PANOPTICON_SYSCONFIG_OPT_SMTPSECURE_SSL',
                            'tls' => 'PANOPTICON_SYSCONFIG_OPT_SMTPSECURE_TLS',
                        ],
                        name: 'options[smtpsecure]',
                        attribs: [
                            'class' => 'form-select',
                            'required' => 'required',
                        ],
                        selected: $config->get('smtpsecure', 'none'),
                        idTag: 'smtpsecure',
                        translate: true
                    ) }}
                </div>
            </div>

            {{--smtpauth--}}
            <div class="row mb-3">
                <div class="col-sm-9 offset-sm-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="options[smtpauth]" id="smtpauth"
                                {{ $config->get('smtpauth', false) ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="smtpauth">
                            @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SMTPAUTH')
                        </label>
                    </div>
                </div>
            </div>

            {{--smtpuser--}}
            <div class="row mb-3">
                <label for="smtpuser" class="col-sm-3 col-form-label">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_SMTPUSER')
                </label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="smtpuser" name="options[smtpuser]"
                           value="{{{ $config->get('smtpuser', '') }}}"
                    >
                </div>
            </div>

            {{--smtppass--}}
            <div class="row mb-3">
                <label for="smtppass" class="col-sm-3 col-form-label">
                    @lang('PANOPTICON_SYSCONFIG_LBL_FIELD_smtppass')
                </label>
                <div class="col-sm-9">
                    <input type="password" class="form-control" id="smtppass" name="options[smtppass]"
                           value="{{{ $config->get('smtppass', '') }}}"
                    >
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-warning">
    <h3 class="card-header h4 text-bg-warning">
        @lang('PANOPTICON_SYSCONFIG_LBL_SUBHEAD_EMAIL_TEST')
    </h3>
    <div class="card-body">
        <p>
            @lang('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_WATCH_OUT')
        </p>
        <div class="text-center my-4">
            <a href="@route(sprintf('index.php?view=sysconfig&task=testemail&%s=1', $this->container->session->getCsrfToken()->getValue()))"
               class="btn btn-outline-warning">
                <span class="fa fa-fw fa-envelope" aria-hidden="true"></span>
                @lang('PANOPTICON_SYSCONFIG_BTN_EMAILTEST_SEND')
            </a>
        </div>
        <p class="small text-muted">
            <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
            @sprintf('PANOPTICON_SYSCONFIG_LBL_EMAILTEST_WILL_SEND_TO', sprintf('<span class="text-body-tertiary">%s</span>', $this->escape($user->getEmail())))
        </p>
    </div>
</div>