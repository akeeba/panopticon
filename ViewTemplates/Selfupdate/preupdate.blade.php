<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */

?>

<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-info border-info rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-hourglass-start" aria-hidden="true" id="hourglass"></span>
    </div>
    <h3 class="display-4">
        @lang('PANOPTICON_SELFUPDATE_LBL_PREUPDATE_WAIT')
    </h3>
</div>
