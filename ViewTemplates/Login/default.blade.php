<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Text\Text;
use Awf\Utils\Template;

// Used for type hinting
/** @var  \Akeeba\Panopticon\View\Login\Html $this */

$css = <<< CSS
svg.panopticonLogoColour {height: 6em;margin-bottom: 1em;}
CSS;

$canPWReset = Factory::getContainer()->appConfig->get('pwreset', true);

?>
@inlinecss($css)

<form action="@route('index.php?view=login&task=login')"
      class="d-flex flex-column justify-content-center align-items-center m-0 p-0"
      method="POST" id="loginForm">

    <header class="mb-4 text-center">
        {{ file_get_contents(APATH_MEDIA . '/images/logo_colour.svg') }}
        <h3 class="h2 text-center text-primary-emphasis">
            @lang('PANOPTICON_LOGIN_LBL_PLEASELOGIN')
        </h3>
    </header>

    <div class="w-75 border rounded p-3 bg-light-subtle" id="loginArea">
        <div class="form-floating mb-3">
            <input type="text" id="username" name="username" class="form-control"
                   placeholder="@lang('PANOPTICON_LOGIN_LBL_USERNAME')" required autofocus
                   value="{{{ $this->username ?? '' }}}">
            <label for="username">@lang('PANOPTICON_LOGIN_LBL_USERNAME')</label>
        </div>

        <div class="form-floating mb-3">
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="@lang('PANOPTICON_LOGIN_LBL_PASSWORD')" required
                   value="{{{ $this->password ?? '' }}}">
            <label for="password">@lang('PANOPTICON_LOGIN_LBL_PASSWORD')</label>
        </div>

        <div class="d-flex flex-row gap-3">
            <button type="submit" class="w-100 btn btn-primary btn-lg"
                    id="btnLoginSubmit">
                <span class="fa fa-user-check me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_LOGIN_LBL_LOGIN')
            </button>

            @if($this->hasPasskeys)
                <button type="button"
                        class="w-100 btn btn-secondary btn-lg border-primary border-2 passkey_login_button"
                        id="btnPasskeyLogin">
                    {{ @file_get_contents(Template::parsePath('media://images/passkey-white.svg', true, $this->getContainer()->application)) }}
                    <span class="ms-1">
                    @lang('PANOPTICON_PASSKEYS_BTN_LOGIN')
                </span>
                </button>
            @endif
        </div>

        <div class="mt-4 mb-1 d-flex flex-row {{ ($canPWReset || $this->registrationEnabled) ? '' : 'justify-content-end' }}">
            @if ($canPWReset || $this->registrationEnabled)
            <div class="flex-grow-1 d-flex flex-column">
                @if ($canPWReset)
                <a href="@route('index.php?view=users&task=pwreset')"
                   class="btn btn-link text-decoration-none p-0 mb-1 text-start"
                >@lang('PANOPTICON_USERS_LBL_PWRESET_PROMPT')</a>
                @endif
                @if ($this->registrationEnabled)
                <a href="@route('index.php?view=users&task=register')"
                   class="btn btn-link text-decoration-none p-0 text-start"
                >@lang('PANOPTICON_USERS_LBL_REGISTER_PROMPT')</a>
                @endif
            </div>
            @endif

            <div>
                <label for="language" class="visually-hidden">
                    @lang('PANOPTICON_LOGIN_LBL_LANGUAGE')
                </label>
                {{ $this->getContainer()->helper->setup->languageOptions(
                    $this->getContainer()->segment->get('panopticon.forced_language', ''),
                    name: 'language',
                    id: 'language',
                    attribs: ['class' => 'form-select', 'style' => 'width: min(19em, 100%)'],
                    addUseDefault: true,
                    namesAlsoInEnglish: false
                ) }}
            </div>
        </div>

        <div class="mt-3 text-center small">
            <a href="@route('index.php?view=policies&task=tos')" class="text-decoration-none" target="_blank">
                @lang('PANOPTICON_POLICIES_TITLE_TOS')
            </a>
            <span class="text-muted mx-1">|</span>
            <a href="@route('index.php?view=policies&task=privacy')" class="text-decoration-none" target="_blank">
                @lang('PANOPTICON_POLICIES_TITLE_PRIVACY')
            </a>
        </div>

        <input type="hidden" name="token" value="@token()">
        <input type="hidden" name="return"
               value="<?= empty($this->returnUrl) ? '' : base64_encode($this->returnUrl) ?>">
    </div>
</form>

@if ($this->autologin)
    <script type="text/javascript">
        window.addEventListener('DOMContentLoaded', () =>
        {
            document.getElementById('loginForm').submit();
        });
    </script>
@endif
