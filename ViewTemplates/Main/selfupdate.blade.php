<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Main\Html   $this
 * @var \Akeeba\Panopticon\Model\Selfupdate $model
 */

?>
@if ($this->selfUpdateInformation->stuck)
    <div class="alert alert-danger d-flex flex-row justify-content-between align-items-center">
        <div>
            <span class="fa fa-xmark-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_LBL_STUCK_HEAD')
        </div>
        <div>
            <a class="btn btn-info btn-sm" role="button"
               href="@route('index.php?view=selfupdate')">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SELFUPDATE_LBL_MAIN_MORE_INFO')
            </a>
        </div>
    </div>
@elseif (!$this->selfUpdateInformation->loadedUpdate)
    <div class="alert alert-warning d-flex flex-row justify-content-between align-items-center">
        <div class="fs-3">
            <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_LBL_NOT_LOADED_HEAD')
        </div>
        <div>
            <a class="btn btn-info btn-sm" role="button"
               href="@route('index.php?view=selfupdate')">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SELFUPDATE_LBL_MAIN_MORE_INFO')
            </a>
        </div>
    </div>
@elseif ($this->hasSelfUpdate)
    <div class="alert alert-info d-flex flex-row justify-content-between align-items-center">
        <div class="fs-3">
            <span class="fa fa-file-zipper" aria-hidden="true"></span>
            @sprintf('PANOPTICON_SELFUPDATE_LBL_UPDATE_HEAD', $this->latestPanopticonVersion->version)
        </div>
        <div>
            <a class="btn btn-info btn-sm" role="button"
               href="@route('index.php?view=selfupdate')">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SELFUPDATE_LBL_MAIN_MORE_INFO')
            </a>
        </div>
    </div>
@endif