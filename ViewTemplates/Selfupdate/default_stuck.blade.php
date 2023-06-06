<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */
?>
<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-danger border-danger rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-xmark-circle" aria-hidden="true"></span>
    </div>
    <h3 class="display-3">
        @lang('PANOPTICON_SELFUPDATE_LBL_STUCK_HEAD')
    </h3>
</div>

<div class="mt-3 alert alert-danger">
    <p class="fw-bold">
        @lang('PANOPTICON_SELFUPDATE_LBL_STUCK_LAST_ERROR')
    </p>
    <p class="font-monospace">
        {{{ $this->updateInformation->error }}}
    </p>

    @if ($this->container->appConfig->get('debug', false))
        <details>
            <summary class="fw-medium">@lang('PANOPTICON_SELFUPDATE_LBL_STUCK_DEBUG_TRACE')</summary>
            <pre>{{{ $this->updateInformation->errorLocation }}}

{{{ $this->updateInformation->errorTraceString }}}</pre>
        </details>
    @endif
</div>

<div class="my-5 d-flex flex-row justify-content-center">
    <a class="btn btn-lg btn-outline-secondary" role="button"
            href="@route('index.php?view=selfupdate&force=1')">
        <span class="fa fa-refresh" aria-hidden="true"></span>
        @lang('PANOPTICON_SELFUPDATE_BTN_RELOAD')
    </a>
</div>