<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Users\Html $this
 * @var \Akeeba\Panopticon\Model\Users     $item
 */
$item       = $this->getModel();
$hasAvatars = $this->getContainer()->appConfig->get('avatars', false);
$amISuper   = $this->getContainer()->userManager->getUser()->getPrivilege('panopticon.super');
?>

<div class="d-flex flex-column flex-lg-row gap-4 p-2 my-3 align-items-center">

    @if($hasAvatars)
    <div class="text-center flex-shrink-1 d-flex flex-column gap-2">
        <img src="{{{ $item->getAvatar(384) }}}" class="rounded-circle" alt="" style="max-width: 192px">
        <?php $editURL = $item->getAvatarEditUrl(); ?>
        @unless(empty($editURL))
        <div>
            <a href="{{ $editURL }}" target="_blank" class="btn btn-secondary" role="button">
                @lang('PANOPTICON_USERS_LBL_AVATAR_EDIT')
                <span class="fa fa-external-link" aria-hidden="true"></span>
            </a>
        </div>
        @endunless
    </div>
    @else
        <div class="text-center flex-shrink-1 d-flex flex-column gap-2">
            <div class="rounded-circle bg-light py-5 px-5">
                <span class="fa fa-user-large display-1 rounded-circle bg-light" aria-hidden="true"></span>
            </div>
        </div>
    @endif

    <div class="flex-grow-1">
        <p class="display-4 text-center">
            {{{ $item->name }}}
        </p>

        <div class="d-flex flex-column flex-md-row gap-3 justify-content-evenly">
            <div class="d-flex flex-column">
                <strong>
                    @lang('PANOPTICON_USERS_TABLE_HEAD_USERNAME')
                </strong>
                <span class="text-info font-monospace fw-medium">
                    {{{ $item->username }}}
                </span>
            </div>
            <div class="d-flex flex-column">
                <strong>
                    @lang('PANOPTICON_USERS_LBL_FIELD_EMAIL')
                </strong>
                <span class="font-monospace">
                    {{{ $item->email }}}
                </span>
            </div>
        </div>

        <div class="mt-4 d-flex flex-row gap-3 justify-content-center">
            <a href="@route(sprintf('index.php?view=users&task=edit&id=%d', $item->id))" class="btn btn-primary" role="button">
                <span class="fa fa-user-pen" aria-hidden="true"></span>
                @lang('PANOPTICON_USERS_LBL_EDIT_PROFILE')
            </a>

            <a href="@route('index.php?view=login&task=logout')" class="btn btn-danger" role="button">
                <span class="fa fa-right-from-bracket" aria-hidden="true"></span>
                @lang('PANOPTICON_APP_LBL_LOGOUT')
            </a>

        </div>

    </div>

</div>
