<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Users\Html $this */

?>

<form action="@route('index.php?view=users&task=activate')" method="post">
    <input type="hidden" name="id" value="<?= intval($this->user->getId()) ?>">

    <div class="card card-body">
        <h2 class="card-title mb-4">
            @lang('PANOPTICON_USERS_LBL_ACTIVATE_HEAD')
        </h2>

        <p class="mb-3">
            @lang('PANOPTICON_USERS_LBL_ACTIVATE_INFO')
        </p>

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
            <label for="token" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_ACTIVATE_TOKEN')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="token" id="token"
                       value="{{ $this->token ?? '' }}" required
                >
            </div>
        </div>

        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" class="btn btn-primary">
                    <span class="fa fa-check-circle me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_USERS_LBL_ACTIVATE_SUBMIT_BUTTON')
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
