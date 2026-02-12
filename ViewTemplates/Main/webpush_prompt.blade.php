<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

$user = $this->container->userManager->getUser();

// Don't show if user is not logged in
if (!$user->getId())
{
	return;
}

// Don't show if user has declined
$promptState = $user->getParameters()->get('webpush.prompt_state', '');

if ($promptState === 'declined')
{
	return;
}

// Don't show if user said "remind me later" and the time hasn't passed
if ($promptState === 'remind')
{
	$promptUntil = (int) $user->getParameters()->get('webpush.prompt_until', 0);

	if ($promptUntil > time())
	{
		return;
	}
}

?>
<div id="webpush-prompt" class="alert alert-info alert-dismissible fade show webpush-requires-support" role="alert">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
        <div class="flex-grow-1">
            <span class="fa fa-bell me-2" aria-hidden="true"></span>
            @lang('PANOPTICON_WEBPUSH_PROMPT_MESSAGE')
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-primary btn-sm" id="webpush-prompt-enable">
                <span class="fa fa-check me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_WEBPUSH_PROMPT_BTN_ENABLE')
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="webpush-prompt-remind">
                <span class="fa fa-clock me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_WEBPUSH_PROMPT_BTN_REMIND')
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="webpush-prompt-decline">
                <span class="fa fa-times me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_WEBPUSH_PROMPT_BTN_DECLINE')
            </button>
        </div>
    </div>
</div>
