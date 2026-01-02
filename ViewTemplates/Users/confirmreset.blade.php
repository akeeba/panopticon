<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Users\Html $this */

?>

<form action="@route('index.php?view=users&task=confirmreset')" method="post">
    <input type="hidden" name="id" value="<?= intval($this->user->getId()) ?>">

    <div class="card card-body">
        <h2 class="card-title mb-4">
            @lang('PANOPTICON_USERS_LBL_CONFIRMRESET_HEAD')
        </h2>

        <div class="row my-2">
            <label for="token" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_CONFIRMRESET_TOKEN')
            </label>
            <div class="col-sm-9">
                <input type="text" class="form-control" name="token" id="token"
                       value="{{ $this->token ?? '' }}" required
                >
            </div>
        </div>

        <div class="row my-2">
            <label for="password" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_USERS_LBL_CONFIRMRESET_PASSWORD')
            </label>
            <div class="col-sm-9">
                <input type="password" class="form-control" name="password" id="password"
                       value="" required
                >
            </div>
        </div>

        <div class="row my-2">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" class="btn btn-primary">
                    @lang('PANOPTICON_USERS_LBL_CONFIRMRESET_SUBMIT_BUTTON')
                </button>
            </div>
        </div>

    </div>
</form>
