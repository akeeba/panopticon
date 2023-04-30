<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('AKEEBA') or die();

// Used for type hinting
/** @var  \Akeeba\Panopticon\View\Login\Html  $this */

?>
<form role="form" action="@route('index.php?view=login&task=login')"
      class="vh-100 d-flex flex-column justify-content-center align-items-center m-0 p-0"
      method="POST" id="loginForm">

    <header class="mb-4">
        <h3 class="h2 text-center text-primary-emphasis">
            @lang('PANOPTICON_LOGIN_LBL_PLEASELOGIN')
        </h3>
    </header>

    <div class="w-75 border rounded p-3 bg-light-subtle">
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

        @if (!defined('AKEEBADEBUG'))
            <div class="form-floating mb-3">
                <input type="text" name="secret" id="secret" class="form-control"
                       placeholder="@lang('PANOPTICON_LOGIN_LBL_SECRETCODE')"
                       value="{{{ $this->secret ?? '' }}}">
                <label for="secret">@lang('PANOPTICON_LOGIN_LBL_SECRETCODE')</label>
            </div>
        @endif

        <button type="submit" class="w-100 btn btn-primary btn-lg text-white"
                id="btnLoginSubmit">
            <span class="fa fa-user-check" aria-hidden="true"></span>
            @lang('PANOPTICON_LOGIN_LBL_LOGIN')
        </button>

        <input type="hidden" name="token" value="@token()">
    </div>
</form>

@if ($this->autologin)
<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('loginForm').submit();
    });
</script>
@endif
