<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Logs\Html $this
 */

?>

<div class="card card-body border-danger p-5 my-5 mx-3 text-center bg-danger-subtle text-danger">
    <h3 class="display-1 text-danger-emphasis mb-5">
        <span class="fa fa-fw fa-warning" aria-hidden="true"></span>
        @lang('PANOPTICON_LOGS_ERR_NOTHING_TO_SEE')
    </h3>

    <p class="display-6 lh-lg">
        @lang('PANOPTICON_LOGS_ERR_NOTHING_TO_SEE_DETAILS')
    </p>
</div>