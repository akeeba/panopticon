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


<h4>Legal Information</h4>

<p>
    @lang('PANOPTICON_APP_TITLE') — Self–hosted site monitoring.
    <br />
    Copyright &copy; {{  $copyrightYear }} Akeeba Ltd
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
    Akeeba Panopticon comes with additional Terms and Conditions on top of the GNU Affero General Public License version 3. Nothing scary, just common sense stuff. Please read the <a class="text-body-tertiary" href="{{ \Awf\Uri\Uri::base() }}LICENSE.txt">LICENSE.txt</a> file supplied with the software.
</div>

<h4>Third Party Software Included</h4>

<p>Panopticon does not exist in a vacuum. It makes use of third party, Free and Open Source Software packages. Here are the packages, and their corresponding versions, installed with this version of Panopticon.</p>

<div class="alert alert-info">
    <p>This information presented below is read directly from the Composer and Node.js Package Manager (NPM) lock files used to build this version of the software. Therefore, the information below is equivalent to a Software Bill Of Materials (SBOM).</p>
    <p>You can view a detailed dependency analysis, and download the formal SBOM in JSON format, of the latest <em>in-development</em> version at <a href="https://github.com/akeeba/panopticon/network/dependencies">the GitHub reporsitory of Panopticon</a>. Please note that most Node (NPM) dependencies are <em>development</em> dependencies which are not shipped with the software. The dependencies used in production code are listed below.</p>
</div>

<h5>PHP Dependencies</h5>

<table class="table">
    <thead class="table-dark">
    <tr>
        <th>Package</th>
        <th>Version</th>
        <th>Reference</th>
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

<h5>CSS and JavaScript Dependencies</h5>
<table class="table">
    <thead class="table-dark">
    <tr>
        <th>Package</th>
        <th>Version</th>
        <th>Integrity</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Bootstrap</td>
        <td>
            {{ $npmInfo['packages']['node_modules/bootstrap']['version'] }}
        </td>
        <td>
            {{ $integrity($npmInfo['packages']['node_modules/bootstrap']['integrity']) }}
        </td>
    </tr>
    <tr>
        <td>FontAwesome</td>
        <td>
            {{ $npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['version'] }}
        </td>
        <td>
            {{ $integrity($npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['integrity']) }}
        </td>
    </tr>
    <tr>
        <td>Cloud9 ACE editor</td>
        <td>
            {{ $npmInfo['packages']['node_modules/ace-builds']['version'] }}
        </td>
        <td>
            {{ $integrity($npmInfo['packages']['node_modules/ace-builds']['integrity']) }}
        </td>
    </tr>
    <tr>
        <td>choices.js</td>
        <td>
            {{ $npmInfo['packages']['node_modules/choices.js']['version'] }}
        </td>
        <td>
            {{ $integrity($npmInfo['packages']['node_modules/choices.js']['integrity']) }}
        </td>
    </tr>
    <tr>
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

<h4 class="mt-4">Software Lifetime, Support, Bug Fixes, and Security Policy</h4>
<h5>Supported Versions</h5>
<p>
    Akeeba Ltd actively supports only the versions of this software distributed from the <a href="https://github.com/akeeba/panopticon/releases" target="_blank">Releases page</a> of its GitHub repository, and only the latest published stable (not marked as “Testing”) version. Upon release of a new stable version, all past versions (stable, and testing) are immediately considered out-of-support.
</p>
<p>
    This software can be extended with code provided by the person or entity managing this installation. Any functional issue (bug) or security issue which may arise from such third party code is explicitly considered the sole responsibility of the person or entity managing this installation, and they carry the full liability for it.
</p>

<h5>Bug and Security Fixes</h5>
<p>
    Supported versions receive bug fixes and security fixes in the form of new versions (updates). We provide information about the latest released version through GitHub's Releases feature in our aforementioned GitHub repository. This is the only canonical resource for discovering updates. Discovery and timely installation of these updates is the user's responsibility.
</p>

<h5>PHP support</h5>
<p>
    As a general rule, Akeeba Ltd only supports installing and using this software on the latest version of the PHP branches which are currently shown as being in “Active Support” in <a href="https://www.php.net/supported-versions" target="_blank">the Supported Versions page</a> of the PHP language's official site.
</p>
<p>
    Because of our dependency on third party code, we may have to drop support for some versions of PHP before their end of life. Likewise, we may not be able to provide full or even partial support for new PHP versions within a predictable timeframe after their stable release; we are beholden to the release schedule of the third party dependencies.
</p>

<h5>End User Support</h5>
<p>
    Akeeba Ltd offers priority end user support only on a subscription basis from our site, <a href="https://www.akeeba.com">https://www.akeeba.com</a>, and only through its Support ticket system.
</p>
<p>
    End users may seek peer support in the Discussions section of Panopticon's aforementioned GitHub repository. We may occasionally post replies there, but we cannot guarantee a service level — we peruse the Discussions in our very limited free time, and it is more than likely that none will be had for a stretch of days to weeks during the peak seasons of our business activities. Please do <em>not</em> use the Issues to seek support; your request will be converted to a Discussion. Moreover, if you are a subscriber to our priority end user support service, please do not use GitHub to request support; we cannot know if you are a subscriber or not when viewing Issues and Discussions on GitHub.
</p>

<h5>Bug Reports</h5>
<p>
    Please make sure that your issue is reproducible with a new Panopticon and CMS installation. If it can only be reproduced with your specific Panopticon or CMS installation your request will be treated as end user support. If you have found a reproducible functional issue (bug) please use the Issues feature of the software's code repository in GitHub to report it. Please keep in mind the <a href="https://github.com/akeeba/panopticon/blob/main/.github/SUPPORT.md" target="_blank">support policy</a> of the software when doing so.
</p>

<h5>Security Issue Reports</h5>
<p>
    If you have found a security issue please <em>do not</em> report it in public! Instead, please follow the instructions detailed in our <a href="https://github.com/akeeba/panopticon/security/policy" target="_blank">Security Policy</a> for this software.
</p>
