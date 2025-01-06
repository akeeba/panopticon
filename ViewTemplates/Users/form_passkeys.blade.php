<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\View\Users\Html;
use Awf\Utils\Template;

/**
 * @var Html    $this        The view object
 * @var ?User   $user        The user we are displaying passkeys for
 * @var bool    $allow_add   Are we allowed to administer the user's passkeys
 * @var array   $credentials Stored passkeys
 * @var ?string $error       Error message to display
 * @var bool    $showImages  Should I display authenticator images?
 */

$container = $this->getContainer();
$user      ??= $container->userManager->getUser();
$lang      = $container->language;
$hasGMP    = function_exists('gmp_intval') !== false;
$hasBcMath = function_exists('bccomp') !== false;

if (!$allow_add)
{
	$error     = $lang->text('PANOPTICON_PASSKEYS_ERR_CANNOT_ADD_FOR_A_USER');
	$allow_add = false;
}
elseif (!$hasBcMath && !$hasBcMath)
{
	$error     = $lang->text('PANOPTICON_PASSKEYS_ERR_WEBAUTHN_REQUIRES_GMP_OR_BCMATCH');
	$allow_add = false;
}

?>
<div class="card">
    <div class="card-body">
        <h3 class="card-title mb-3">
            <span class="fa fa-key" aria-hidden="true"></span>
            @lang('PANOPTICON_PASSKEYS_TITLE')
        </h3>

        @if(($this->collapseForPasskey ?? false) && !count($this->passkeyVariables['credentials']))
            <div class="alert alert-warning">
                <span class="fa fa-warning" aria-hidden="true"></span>
                @lang('PANOPTICON_PASSKEYS_LBL_FORCED_NEEDED')
            </div>
        @elseif(($this->collapseForPasskey ?? false))
            <div class="alert alert-info">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_PASSKEYS_LBL_FORCED_COMPLETE')
            </div>
        @else
            {{-- What is this --}}
            <div class="form-text mb-2 p-2">
                @lang('PANOPTICON_PASSKEYS_DESCRIPTION')
            </div>
        @endif

        <div>
            @if (is_string($error ?? '') && !empty($error ?? ''))
                <div class="alert alert-danger">
                    {{{ $error }}}
                </div>
            @endif

            <table class="table table-striped">
                <caption class="visually-hidden">
                    @lang('PANOPTICON_PASSKEYS_TABLE_CAPTION')
                </caption>
                <thead class="table-dark">
                <tr>
                    <th scope="col">
                        @lang('PANOPTICON_PASSKEYS_MANAGE_FIELD_KEYLABEL_LABEL')
                    </th>
                    <th scope="col" class="text-end">
                        @lang('PANOPTICON_PASSKEYS_MANAGE_HEADER_ACTIONS_LABEL')
                    </th>
                </tr>
                </thead>
                <tbody>
                @foreach ($credentials as $method)
                    <tr data-credential_id="<?= $method['id'] ?>">
                        <th scope="row" class="passkey-cell">
                        <span class="passkey-label flex-grow-1">
                            {{{ $method['label'] }}}
                        </span>
                        </th>
                        <td class="passkey-cell w-35 text-end">
                            <button class="passkey-manage-edit btn btn-secondary m-1" type="button">
                                <span class="icon-edit " aria-hidden="true"></span>
                                @lang('PANOPTICON_PASSKEYS_MANAGE_BTN_EDIT_LABEL')
                            </button>
                            <button class="passkey-manage-delete btn btn-danger m-1" type="button">
                                <span class="icon-minus" aria-hidden="true"></span>
                                @lang('PANOPTICON_PASSKEYS_MANAGE_BTN_DELETE_LABEL')
                            </button>
                        </td>
                    </tr>
                @endforeach
                @if (empty($credentials))
                    <tr>
                        <td colspan="2">
                            @lang('PANOPTICON_PASSKEYS_MANAGE_HEADER_NOMETHODS_LABEL')
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>

            @if ($allow_add)
                <div class="passkey-manage-add-container mt-3 mb-2 d-flex">
                    <div class="flex-grow-1 mx-2 d-flex flex-column align-items-center">
                        <button
                                type="button"
                                id="passkey-manage-addresident"
                                class="btn btn-dark w-100"
                        >
                            {{ file_get_contents(Template::parsePath('media://images/passkey-white.svg', true, $this->getContainer()->application)) }}
                            <span class="ms-1">
                                @lang('PANOPTICON_PASSKEYS_MANAGE_BTN_ADDRESIDENT_LABEL')
                            </span>
                        </button>
                    </div>

                    <div class="flex-grow-1 mx-2 d-flex flex-column align-items-center d-none">
                        <button
                                type="button"
                                id="passkey-manage-add"
                                class="btn btn-outline-dark w-100"
                        >
                            {{ @file_get_contents(Template::parsePath('media://images/webauthn.svg', true, $this->getContainer()->application)) }}
                            <span class="ms-1">
                                @lang('PANOPTICON_PASSKEYS_MANAGE_BTN_ADD_LABEL')
                            </span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
