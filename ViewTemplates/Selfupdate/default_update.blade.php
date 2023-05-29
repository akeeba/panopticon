<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Version\Version;
use Awf\Html\Html;

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */

?>
<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-info border-info rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-file-zipper" aria-hidden="true"></span>
    </div>
    <h3 class="display-3">
        @sprintf('PANOPTICON_SELFUPDATE_LBL_UPDATE_HEAD', $this->latestversion->version)
    </h3>
</div>

<p class="text-center fs-4 my-3">
    <span class="text-muted">{{ AKEEBA_PANOPTICON_VERSION }}</span>
    <span class="fa fa-arrow-right" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SELFUPDATE_LBL_UPDATE_BODY_SR')</span>
    <span class="text-success">{{ $this->latestversion->version }}</span>
</p>

<div class="my-5 d-flex flex-row justify-content-center align-items-center gap-3">
    <div>
        <a class="btn btn-primary btn-lg" role="button"
           href="@route('index.php?view=selfupdate&task=update')">
            <span class="fa fa-play" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_LBL_UPGRADE_BTN')
        </a>
    </div>
    <div>
        <a class="btn btn-outline-secondary" role="button"
           href="@route('index.php?view=selfupdate&force=1')">
            <span class="fa fa-refresh" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_BTN_RELOAD')
        </a>
    </div>
</div>