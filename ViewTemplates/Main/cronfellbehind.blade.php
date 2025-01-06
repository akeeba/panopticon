<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/** @var \Akeeba\Panopticon\View\Main\Html $this */

defined('AKEEBA') || die;

if (empty($this->cronSecondsBehind)) return;
?>

<div class="alert alert-warning">
    <h3 class="h5 alert-heading">
        @lang('PANOPTICON_MAIN_LBL_FALL_BEHIND')
    </h3>
    <div>
        @sprintf('PANOPTICON_MAIN_LBL_FALL_BEHIND_MESSAGE', $this->cronSecondsBehind)
    </div>
    <div>
        <a href="https://github.com/akeeba/panopticon/wiki/CRON-Jobs#busy-installations-will-need-more-than-one-cron-job"
           class="btn btn-info mt-2">
            <span class="fa fa-fw fa-book-open me-2" aria-hidden="true"></span>
            @lang('PANOPTICON_MAIN_LBL_FALL_BEHIND_READ_DOCS')
        </a>
    </div>
</div>