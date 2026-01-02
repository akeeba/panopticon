<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Users\Html $this */

?>

<form action="@route('index.php?view=users&task=pwreset')" method="post">
    <div class="card card-body">
        <h2 class="card-title mb-4">
            @lang('PANOPTICON_USERS_LBL_PWRESET_HEAD')
        </h2>

        <p class="mb-3">
            @lang('PANOPTICON_USERS_LBL_PWRESET_INFO')
        </p>

        <div class="row my-2">
            <label for="username" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_USERNAME')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="username" id="username"
                       value="{{{ $model->username ?? '' }}}" required
                >
            </div>
        </div>
        <div class="row my-2">
            <label for="email" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_FIELD_EMAIL')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="email" id="email"
                       value="{{{ $model->email ?? '' }}}" required
                >
            </div>
        </div>

        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" class="btn btn-primary">
                    @lang('PANOPTICON_USERS_LBL_PWRESET_SUBMIT_BUTTON')
                </button>
            </div>
        </div>

    </div>
</form>
