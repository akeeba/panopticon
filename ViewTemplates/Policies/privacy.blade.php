<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Policies\Html $this */

?>

<div class="container my-4">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title m-0">
                <span class="fa fa-shield-halved me-2" aria-hidden="true"></span>
                @lang('PANOPTICON_POLICIES_TITLE_PRIVACY')
            </h3>
        </div>
        <div class="card-body">
            {{ $this->policyContent }}
        </div>
    </div>

    <p class="mt-3 text-center">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <span class="fa fa-arrow-left me-1" aria-hidden="true"></span>
            @lang('PANOPTICON_BTN_PREV')
        </a>
    </p>
</div>
