<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\About\Html $this */

?>

<h4 class="mt-3">@lang('PANOPTICON_ABOUT_LBL_CONTRIBUTORS')</h4>

<p class="small text-muted">@lang('PANOPTICON_ABOUT_LBL_CONTRIBUTORS_INFO')</p>

@if (!empty($this->contributors))
<div class="row row-cols-2 row-cols-md-5 g-4">
    @foreach($this->contributors as $contributor)
    <div class="col">
        <div class="card h-100 text-center">
            <img src="{{{ $contributor->avatar_url }}}" class="card-img-top"
                alt="@sprintf('PANOPTICON_ABOUT_LBL_AVATAR_FOR', $this->escape($contributor->login))">
            <div class="card-body">
                <h5 class="card-title text-info">
                    {{{ $contributor->login }}}
                </h5>
                <p class="card-text">
                    @sprintf('PANOPTICON_ABOUT_LBL_CONTRIBUTIONS', $contributor->contributions)
                </p>
            </div>
            <div class="card-footer bg-transparent border-top-0">
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
    </div>
    @endforeach
</div>
@endif

<p>
    @sprintf('PANOPTICON_ABOUT_LBL_VISIT_GITHUB', 'https://github.com/akeeba/panopticon/graphs/contributors')
</p>

<div class="alert alert-info">
    <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
    @lang('PANOPTICON_ABOUT_LBL_ARE_YOU_MISSING')
</div>