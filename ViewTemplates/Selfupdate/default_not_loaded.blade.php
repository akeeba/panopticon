<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */
?>
<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-warning border-warning rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
    </div>
    <h3 class="display-3">
        @lang('PANOPTICON_SELFUPDATE_LBL_NOT_LOADED_HEAD')
    </h3>
</div>

<div class="mt-3 text-center fs-5">
    <p>
        @sprintf('PANOPTICON_SELFUPDATE_LBL_NOT_LOADED_BODY', $this->getModel()->getUpdateStreamUrl())
    </p>
</div>

<div class="my-5 d-flex flex-row justify-content-center">
    <a class="btn btn-lg btn-outline-secondary" role="button"
       href="@route('index.php?view=selfupdate&force=1')">
        <span class="fa fa-refresh" aria-hidden="true"></span>
        @lang('PANOPTICON_SELFUPDATE_BTN_RELOAD')
    </a>
</div>