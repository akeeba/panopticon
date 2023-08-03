<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') or die();

use Awf\Text\Text;

// Used for type hinting
/** @var  \Akeeba\Panopticon\View\Login\Html  $this */

$css = <<< CSS
svg.panopticonLogoColour {height: 6em;margin-bottom: 1em;}
CSS;

?>
@inlinecss($css)

<form action="@route('index.php?view=login&task=login')"
      class="vh-100 d-flex flex-column justify-content-center align-items-center m-0 p-0"
      method="POST" id="loginForm">

    <header class="mb-4 text-center">
	    {{ file_get_contents(APATH_MEDIA . '/images/logo_colour.svg') }}
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

        <button type="submit" class="w-100 btn btn-primary btn-lg"
                id="btnLoginSubmit">
            <span class="fa fa-user-check me-1" aria-hidden="true"></span>
            @lang('PANOPTICON_LOGIN_LBL_LOGIN')
        </button>

        <input type="hidden" name="token" value="@token()">
        <input type="hidden" name="return" value="<?= empty($this->returnUrl) ? '' : base64_encode($this->returnUrl) ?>">
    </div>
</form>

@if ($this->autologin)
<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('loginForm').submit();
    });
</script>
@endif
