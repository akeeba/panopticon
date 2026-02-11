<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Policies\Html $this */

$token = $this->container->session->getCsrfToken()->getValue();

?>

<form action="@route('index.php?view=policies&task=save')" method="post"
      name="adminForm" id="adminForm" class="py-3"
>

    <div class="row mb-3">
        <label for="type" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_POLICIES_LBL_TYPE')
        </label>
        <div class="col-sm-9">
            <select name="type" id="type" class="form-select"
                    onchange="document.location='@route('index.php?view=policies&task=edit')' + '&type=' + this.value + '&language=' + document.getElementById('language').value">
                <option value="tos" {{ $this->policyType === 'tos' ? 'selected' : '' }}>
                    @lang('PANOPTICON_POLICIES_TITLE_TOS')
                </option>
                <option value="privacy" {{ $this->policyType === 'privacy' ? 'selected' : '' }}>
                    @lang('PANOPTICON_POLICIES_TITLE_PRIVACY')
                </option>
            </select>
        </div>
    </div>

    <div class="row mb-3">
        <label for="language" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_POLICIES_LBL_LANGUAGE')
        </label>
        <div class="col-sm-9">
            {{ $this->getContainer()->helper->setup->languageOptions(
                    $this->policyLanguage,
                    name: 'language',
                    id: 'language',
                    attribs: [
                        'class' => 'form-select',
                        'onchange' => "document.location='" . $this->container->router->route('index.php?view=policies&task=edit') . "&type=" . $this->policyType . "&language=' + this.value",
                    ],
                ) }}
        </div>
    </div>

    <div class="row mb-3">
        <label for="content" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_POLICIES_LBL_CONTENT')
        </label>
        <div class="col-sm-9">
            {{ \Akeeba\Panopticon\Library\Editor\TinyMCE::editor(
                'content',
                $this->policyContent,
                [
                    'id' => 'content',
                    'relative_urls' => true,
                ]
            ) }}
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
            <div class="d-flex flex-row gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="fa fa-save me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_BTN_SAVE')
                </button>
                <a href="@route('index.php?view=sysconfig')" class="btn btn-outline-secondary">
                    <span class="fa fa-xmark me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_BTN_CANCEL')
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
            <div class="d-flex flex-row gap-2">
                <a href="@route('index.php?view=policies&task=tos')" class="btn btn-outline-info btn-sm" target="_blank">
                    <span class="fa fa-eye me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_POLICIES_LBL_PREVIEW_TOS')
                </a>
                <a href="@route('index.php?view=policies&task=privacy')" class="btn btn-outline-info btn-sm" target="_blank">
                    <span class="fa fa-eye me-1" aria-hidden="true"></span>
                    @lang('PANOPTICON_POLICIES_LBL_PREVIEW_PRIVACY')
                </a>
            </div>
        </div>
    </div>

    <input type="hidden" name="@token(true)" value="1">

</form>
