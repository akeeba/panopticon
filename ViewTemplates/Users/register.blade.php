<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Captcha\CaptchaFactory;

/** @var \Akeeba\Panopticon\View\Users\Html $this */

$container       = $this->getContainer();
$captchaProvider = $container->appConfig->get('captcha_provider', 'altcha');

// Generate CAPTCHA challenge
$captcha     = CaptchaFactory::make($captchaProvider, $container);
$captchaHtml = $captcha?->renderChallenge() ?? '';

?>

<form action="@route('index.php?view=users&task=register')" method="post">
    <div class="card card-body">
        <h2 class="card-title mb-4">
            @lang('PANOPTICON_USERS_LBL_REGISTER_HEAD')
        </h2>

        <p class="mb-3">
            @lang('PANOPTICON_USERS_LBL_REGISTER_INFO')
        </p>

        <div class="row my-2">
            <label for="name" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_NAME')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="name" id="name"
                       value="" required autofocus
                >
            </div>
        </div>

        <div class="row my-2">
            <label for="username" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_USERNAME')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="username" id="username"
                       value="" required
                >
            </div>
        </div>

        <div class="row my-2">
            <label for="email" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_EMAIL')
            </label>
            <div class="col-sm-9">
                <input type="email" class="form-control" name="email" id="email"
                       value="" required
                >
            </div>
        </div>

        <div class="row my-2">
            <label for="password" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_PASSWORD')
            </label>
            <div class="col-sm-9">
                <input type="password" class="form-control" name="password" id="password"
                       value="" required
                >
            </div>
        </div>

        <div class="row my-2">
            <label for="password2" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_PASSWORD2')
            </label>
            <div class="col-sm-9">
                <input type="password" class="form-control" name="password2" id="password2"
                       value="" required
                >
            </div>
        </div>

        @if (!empty($captchaHtml))
        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                {{ $captchaHtml }}
            </div>
        </div>
        @endif

        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" class="btn btn-primary">
                    <span class="fa fa-user-plus me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_USERS_LBL_REGISTER_SUBMIT_BUTTON')
                </button>
            </div>
        </div>

        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                <a href="@route('index.php?view=login')" class="btn btn-link text-decoration-none">
                    @lang('PANOPTICON_USERS_LBL_BACK_TO_LOGIN')
                </a>
            </div>
        </div>

    </div>
</form>
