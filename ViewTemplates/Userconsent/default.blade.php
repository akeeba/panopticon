<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Userconsent\Html $this */

$token = $this->container->session->getCsrfToken()->getValue();

?>

<div class="container my-4">
    {{-- Header --}}
    <div class="alert alert-info">
        <h4 class="alert-heading">
            <span class="fa fa-handshake me-2" aria-hidden="true"></span>
            @lang('PANOPTICON_USERCONSENT_HEAD_REQUIRED')
        </h4>
        <p class="mb-0">
            @lang('PANOPTICON_USERCONSENT_LBL_EXPLANATION')
        </p>
    </div>

    {{-- Terms of Service --}}
    <div class="accordion mb-3" id="policyAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingTos">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseTos"
                        aria-expanded="false" aria-controls="collapseTos">
                    <span class="fa fa-file-contract me-2" aria-hidden="true"></span>
                    @lang('PANOPTICON_POLICIES_TITLE_TOS')
                </button>
            </h2>
            <div id="collapseTos" class="accordion-collapse collapse" aria-labelledby="headingTos"
                 data-bs-parent="#policyAccordion">
                <div class="accordion-body" style="max-height: 400px; overflow-y: auto;">
                    {{ $this->tosContent }}
                </div>
                <div class="accordion-footer p-2 text-end border-top">
                    <a href="@route('index.php?view=policies&task=tos')" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <span class="fa fa-external-link me-1" aria-hidden="true"></span>
                        @lang('PANOPTICON_USERCONSENT_LBL_VIEW_FULL')
                    </a>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingPrivacy">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapsePrivacy"
                        aria-expanded="false" aria-controls="collapsePrivacy">
                    <span class="fa fa-shield-halved me-2" aria-hidden="true"></span>
                    @lang('PANOPTICON_POLICIES_TITLE_PRIVACY')
                </button>
            </h2>
            <div id="collapsePrivacy" class="accordion-collapse collapse" aria-labelledby="headingPrivacy"
                 data-bs-parent="#policyAccordion">
                <div class="accordion-body" style="max-height: 400px; overflow-y: auto;">
                    {{ $this->privacyContent }}
                </div>
                <div class="accordion-footer p-2 text-end border-top">
                    <a href="@route('index.php?view=policies&task=privacy')" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <span class="fa fa-external-link me-1" aria-hidden="true"></span>
                        @lang('PANOPTICON_USERCONSENT_LBL_VIEW_FULL')
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Consent Buttons --}}
    <div class="card mb-3">
        <div class="card-body text-center">
            <p class="mb-3">
                @lang('PANOPTICON_USERCONSENT_LBL_AGREE_PROMPT')
            </p>
            <div class="d-flex flex-row gap-3 justify-content-center">
                <form action="@route('index.php?view=userconsent&task=agree')" method="post" class="d-inline">
                    <input type="hidden" name="{{ $token }}" value="1">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span class="fa fa-check me-1" aria-hidden="true"></span>
                        @lang('PANOPTICON_USERCONSENT_BTN_AGREE')
                    </button>
                </form>
                <a href="@route('index.php?view=userconsent&task=decline')" class="btn btn-outline-secondary btn-lg">
                    <span class="fa fa-xmark me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_USERCONSENT_BTN_DECLINE')
                </a>
            </div>
        </div>
    </div>

    {{-- Data Export --}}
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title m-0">
                <span class="fa fa-download me-2" aria-hidden="true"></span>
                @lang('PANOPTICON_USERCONSENT_HEAD_EXPORT')
            </h5>
        </div>
        <div class="card-body">
            <p>
                @lang('PANOPTICON_USERCONSENT_LBL_EXPORT_DESC')
            </p>
            <a href="@route('index.php?view=userconsent&task=export')" class="btn btn-outline-primary">
                <span class="fa fa-file-export me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_USERCONSENT_BTN_EXPORT')
            </a>
        </div>
    </div>

    {{-- Account Deletion --}}
    <div class="card mb-3 border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="card-title m-0">
                <span class="fa fa-triangle-exclamation me-2" aria-hidden="true"></span>
                @lang('PANOPTICON_USERCONSENT_HEAD_DELETE')
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <span class="fa fa-exclamation-circle me-1" aria-hidden="true"></span>
                @lang('PANOPTICON_USERCONSENT_LBL_DELETE_WARNING')
            </div>

            @if (!$this->canSelfDelete)
                <div class="alert alert-danger">
                    <span class="fa fa-ban me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_USERCONSENT_LBL_DELETE_SOLE_ADMIN')
                </div>
            @else
                <form action="@route('index.php?view=userconsent&task=deleteaccount')" method="post"
                      onsubmit="return confirm('{{{ $this->getLanguage()->text('PANOPTICON_USERCONSENT_LBL_DELETE_CONFIRM') }}}')">
                    <input type="hidden" name="{{ $token }}" value="1">

                    <div class="mb-3">
                        <label for="confirm_username" class="form-label">
                            @lang('PANOPTICON_USERCONSENT_LBL_DELETE_TYPE_USERNAME')
                        </label>
                        <input type="text" name="confirm_username" id="confirm_username"
                               class="form-control" required
                               autocomplete="off"
                               placeholder="{{{ $this->username }}}">
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <span class="fa fa-trash me-1" aria-hidden="true"></span>
                        @lang('PANOPTICON_USERCONSENT_BTN_DELETE')
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
