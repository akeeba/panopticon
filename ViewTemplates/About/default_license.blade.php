<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\About\Html $this */

$maxYear       = gmdate('Y');
$copyrightYear = ($maxYear == 2023) ? "2023" : "2023–{$maxYear}";
?>

<h4>@lang('PANOPTICON_ABOUT_LBL_LICENSE')</h4>

<p>
    @lang('PANOPTICON_APP_TITLE') - @lang('PANOPTICON_ABOUT_LBL_APP_SUBTITLE').
    <br />
    @sprintf('PANOPTICON_ABOUT_LBL_COPYRIGHT', $copyrightYear)
</p>

<div class="d-flex flex-column flex-md-row gap-2">
    <div>
        <img src="@media('images/agpl_logo.svg', false, $this->container->application)" class="img-fluid">
    </div>
    <div>
        <p>
            This program is free software: you can redistribute it and/or modify it under the terms of the <a
                    href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank">GNU Affero General Public License</a> as
            published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
        </p>
        <p>
            This program is distributed in the hope that it will be useful, but <strong>WITHOUT ANY WARRANTY</strong>; without
            even the implied warranty of <strong>MERCHANTABILITY</strong> or <strong>FITNESS FOR A PARTICULAR PURPOSE</strong>.
            See the GNU Affero General Public License for more details.
        </p>
        <p>
            You should have received a copy of the GNU Affero General Public License
            along with this program. If not, see <a href="https://www.gnu.org/licenses/" target="_blank">https://www.gnu.org/licenses/</a>
            .
        </p>
    </div>
</div>

<div class="text-body-tertiary my-3 mx-4">
    @sprintf('PANOPTICON_ABOUT_LBL_PLEASE_READ_LICENSE', \Awf\Uri\Uri::base())
</div>