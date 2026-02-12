<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

$user = $this->container->userManager->getUser();

// Only show for the user editing their own profile
if (!$user->getId() || $user->getId() != $this->getModel()->getId())
{
	return;
}

?>
<div class="card my-3 webpush-requires-support">
    <div class="card-header">
        <h3 class="card-title h5 m-0">
            <span class="fa fa-bell me-2" aria-hidden="true"></span>
            @lang('PANOPTICON_WEBPUSH_PROFILE_TITLE')
        </h3>
    </div>
    <div class="card-body">
        <p class="text-muted">
            @lang('PANOPTICON_WEBPUSH_PROFILE_DESC')
        </p>

        <div class="d-flex align-items-center gap-3 mb-3">
            <strong>@lang('PANOPTICON_WEBPUSH_LBL_STATUS'):</strong>
            <span id="webpush-status-badge" class="badge bg-secondary">
                @lang('PANOPTICON_WEBPUSH_LBL_STATUS_INACTIVE')
            </span>
        </div>

        <div class="d-flex gap-2">
            <button type="button"
                    class="btn btn-success"
                    id="webpush-subscribe-btn">
                <span class="fa fa-bell me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_WEBPUSH_BTN_SUBSCRIBE')
            </button>
            <button type="button"
                    class="btn btn-danger"
                    id="webpush-unsubscribe-btn"
                    style="display: none">
                <span class="fa fa-bell-slash me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_WEBPUSH_BTN_UNSUBSCRIBE')
            </button>
        </div>
    </div>
</div>
