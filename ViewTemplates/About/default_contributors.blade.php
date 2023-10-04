<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\About\Html $this */

?>

<h4 class="mt-3">@lang('PANOPTICON_ABOUT_LBL_CONTRIBUTORS')</h4>

<p>@lang('PANOPTICON_ABOUT_LBL_CONTRIBUTORS_INFO')</p>

@if (!empty($this->contributors))
<div class="d-flex flex-row align-items-stretch justify-content-evenly my-4">
    @foreach($this->contributors as $contributor)
    <div class="card" style="max-width: min(33%, 12rem)">
        <img src="{{{ $contributor->avatar_url }}}" class="card-img-top" alt="@sprintf('PANOPTICON_ABOUT_LBL_AVATAR_FOR', $this->escape($contributor->login))">
        <div class="card-body">
            <h5 class="card-title">
                {{{ $contributor->login }}}
            </h5>
            <p class="card-text">
                @sprintf('PANOPTICON_ABOUT_LBL_CONTRIBUTIONS', $contributor->contributions)
            </p>
            <a href="{{{ $contributor->html_url }}}" class="btn btn-outline-info" target="_blank">
                <span class="fab fa-fw fa-github" aria-hidden="true"></span>
                <span aria-hidden="true">
                    @lang('PANOPTICON_ABOUT_LBL_GITHUB_PROFILE')
                </span>
                <span class="visually-hidden">
                    @sprintf('PANOPTICON_ABOUT_LBL_GITHUB_PROFILE_FOR', $this->escape($contributor->login))
                </span>
            </a>
        </div>
    </div>
    @endforeach
</div>
@endif

<p>
    @sprintf('PANOPTICON_ABOUT_LBL_VISIT_GITHUB', 'https://github.com/akeeba/panopticon/graphs/contributors')
</p>