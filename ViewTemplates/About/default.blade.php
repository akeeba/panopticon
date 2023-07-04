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

$npmInfo = json_decode(file_get_contents($this->container->basePath . '/vendor/composer/package-lock.json') ?: '{}', true);
$integrity = function(string $hash): string {
	[$algo, $encoded] = explode('-', $hash, 2);
	$decoded = str_split(bin2hex(base64_decode($encoded)), 4);
	return '<span class="badge bg-secondary me-1">' . strtoupper($algo) . '</span>' .
        implode('<wbr>', $decoded);
}
?>

<div class="card card-body text-center my-4 bg-light-subtle">
    <p class="display-1">
        @lang('PANOPTICON_APP_TITLE')
    </p>
    <p class="display-5 text-secondary">
        {{ AKEEBA_PANOPTICON_VERSION }} <span class="text-body-tertiary">{{ AKEEBA_PANOPTICON_CODENAME }}</span>
    </p>
</div>


<h4>@lang('PANOPTICON_ABOUT_LBL_LICENSE')</h4>

<p>
    @lang('PANOPTICON_APP_TITLE') — @lang('PANOPTICON_ABOUT_LBL_APP_SUBTITLE').
    <br />
    @sprintf('PANOPTICON_ABOUT_LBL_COPYRIGHT', $copyrightYear)
</p>

<div class="d-flex flex-column flex-md-row gap-2">
    <div>
        <img src="@media('images/agpl_logo.svg')" class="img-fluid">
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

<details>
    <summary><h4 class="d-inline-block">@lang('PANOPTICON_ABOUT_LBL_3PD_SOFTWARE')</h4></summary>

    <h5>@lang('PANOPTICON_ABOUT_LBL_PHP_DEPS')</h5>

    <table class="table">
        <thead class="table-dark">
        <tr>
            <th>@lang('PANOPTICON_ABOUT_LBL_PACKAGE')</th>
            <th>@lang('PANOPTICON_ABOUT_LBL_VERSION')</th>
            <th>@lang('PANOPTICON_ABOUT_LBL_REFERENCE')</th>
        </tr>
        </thead>
        <tbody>
        @foreach(\Composer\InstalledVersions::getAllRawData()[0]['versions'] as $packageName => $packageInfo)
				<?php if (!isset($packageInfo['version'])) continue ?>
				<?php if ($packageName === 'akeeba/panopticon') continue ?>
            <tr>
                <td>{{{ $packageName }}}</td>
                <td>{{{ $packageInfo['pretty_version'] ?? $packageInfo['version'] }}}</td>
                <td>{{{ $packageInfo['reference'] }}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h5>@lang('PANOPTICON_ABOUT_LBL_CSS_AND_JS')</h5>
    <table class="table">
        <thead class="table-dark">
        <tr>
            <th>@lang('PANOPTICON_ABOUT_LBL_PACKAGE')</th>
            <th>@lang('PANOPTICON_ABOUT_LBL_VERSION')</th>
            <th>@lang('PANOPTICON_ABOUT_LBL_INTEGRITY')</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>Bootstrap</td>
            <td>
                {{ $npmInfo['packages']['node_modules/bootstrap']['version'] }}
            </td>
            <td>
                {{ $integrity($npmInfo['packages']['node_modules/bootstrap']['integrity']) }}
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>FontAwesome</td>
            <td>
                {{ $npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['version'] }}
            </td>
            <td>
                {{ $integrity($npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['integrity']) }}
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>Cloud9 ACE Editor</td>
            <td>
                {{ $npmInfo['packages']['node_modules/ace-builds']['version'] }}
            </td>
            <td>
                {{ $integrity($npmInfo['packages']['node_modules/ace-builds']['integrity']) }}
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>choices.js</td>
            <td>
                {{ $npmInfo['packages']['node_modules/choices.js']['version'] }}
            </td>
            <td>
                {{ $integrity($npmInfo['packages']['node_modules/choices.js']['integrity']) }}
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>TinyMCE</td>
            <td>
                {{ $npmInfo['packages']['node_modules/tinymce']['version'] }}
            </td>
            <td>
                {{ $integrity($npmInfo['packages']['node_modules/tinymce']['integrity']) }}
            </td>
        </tr>
        </tbody>
    </table>
</details>