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

<details class="mb-4">
    <summary class="h5">Additional Terms and Conditions</summary>

    <div class="small text-muted">
        <p>The following Additional Terms And Conditions are supplementary to the License, as per Article 7 of the license. As
            such, they must be understood to be an integral part of the software license.</p>

        <p>1. Preservation of legal notices</p>

        <p>You are required to preserve all legal notices present in source code files, the About page of the software, and the LICENSE.txt file, as well as any author attributions in the same. You are required to preserve all legal notices and author attributions in all files contained under the vendor directory, in accordance with the software license of each dependency contained therein. This applies to the original work, as well as any copies verbatim, or modified, and any derivative works. While Article 7 of the License allows you to remove this clause, this will most likely violate the software license of the software and / or its dependencies, therefore removing the legal grounds under which you can use and / or convey the software and its dependencies.</p>

        <p>2. Identification of modified copies</p>

        <p>Modified versions must bear prominent notices in both the source code files, README.md file, and the user interface which clearly state that this is no longer the original work and must not be misconstrued as such.</p>

        <p>3. No rights to names, logos, and wordmarks</p>

        <p>Notwithstanding your rights under the fair use doctrine or equivalent laws, the license of this material does
            <em>not</em> grant you any rights to use the “Akeeba” name, logo, and wordmark, the “Akeeba Panopticon” name, and logo, and the “Panopticon” name.</p>

        <p>4. Restrictions on advertising and commercial usage of the names, logos, and wordmarks</p>

        <p>You are disallowed from using the “Akeeba” name, logo, and wordmark, the “Akeeba Panopticon” name, and logo, and the “Panopticon” name for advertising and commercial purposes —including but not limited to advertising campaigns (printed, electronic, or otherwise), web site banners, web site copy, and social media posts— in a way which asserts, implies, or could reasonably be misconstrued as endorsement of your person, business, or product by the licensors and authors of this material. You are permitted to use the aforementioned copyrighted items under the fair use doctrine for factual statements such as “This service makes use of Akeeba Panopticon, a self-hosted site monitoring software created and distributed by Akeeba Ltd”.</p>

        <p>5. Non-transferability of liability and indemnification</p>

        <p>Anyone who this material (or modified versions of it) is conveyed to with contractual or legal assumptions of liability are required to explicitly indemnify the licensors and authors of this software for any liability that these contractual or legal assumptions directly impose on those licensors and authors. A recipient of the software may only remove this clause for the copies they convey to others by assuming the FULL and SOLE LEGAL RESPONSIBILITY and LIABILITY for all copies of this material (or modified versions of it) which have originated directly or indirectly from the copy they conveyed to others.</p>

        <p>6. Support</p>

        <p>The license of the software does not entitle you to any kind of support, or consultancy by the licensor and authors of this software.</p>

        <p>7. Access to services and software</p>

        <p>The license of the software does not entitle you to access of any additional services or software which may be used in conjunction with this software, or required to use this software.</p>
    </div>
</details>

<h4>Third Party Software Included</h4>

<p>Panopticon does not exist in a vacuum. It makes use of third party, Free and Open Source Software packages. Here are the packages, and their corresponding versions, installed with this version of Panopticon.</p>

<div class="alert alert-info">
    This information presented below is read directly from the Composer and Node.js Package Manager (NPM) lock files used to build this version of the software. Therefore, the information below is equivalent to a Software Bill Of Materials (SBOM).
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

<h4 class="mt-4">Software Lifetime, Bug Fixes, and Security Policy</h4>
<p>
    Only the versions of this software distributed by Akeeba Ltd from the <a href="https://github.com/akeeba/panopticon/releases" target="_blank">Releases page</a> of the GitHub repository of this project are considered within scope of bug fixes and security updates, and only in accordance with the stipulations in the next three paragraphs. Versions marked as “pre-release”, “alpha”, “beta”, “release candidate”, “RC”, “development”, or “dev” are explicitly considered outside the scope.
</p>
<p>
    Akeeba Ltd only provides bug fixes and security updates for the latest released version family of the software, unless otherwise explicitly indicated. A version family consists of the major and minor version. Your installation's version family is <strong>{{ \Akeeba\Panopticon\Library\Version\Version::create(AKEEBA_PANOPTICON_VERSION)->versionFamily() }}</strong>.
</p>
<p>
    Akeeba Ltd only supports installing and using this software on the latest version of the PHP branches which are currently supported by the PHP project, unless otherwise specified. These versions are marked as “Security fixes only” and “Active support” on <a href="https://www.php.net/supported-versions" target="_blank">PHP's official site</a>. Any bugs or security issues which can only be reproduced on a version of PHP which is not supported by Akeeba Ltd is considered outside the scope of our development. Your current installation supports PHP {{ AKEEBA_PANOPTICON_MINPHP }} at a minimum.
</p>
<p>
    This software can be extended with code provided by the person or entity managing this installation. Any functional issue (bug) or security issue which may arise from such third party code is explicitly the responsibility of the person or entity managing this installation, and they carry the full liability for it.
</p>
<p>
    If you have found a reproducible functional issue (bug) please use the Issues feature of the software's code repository in GitHub to report it. Please keep in mind the <a href="https://github.com/akeeba/panopticon/blob/main/.github/SUPPORT.md" target="_blank">support policy</a> of the software when doing so.
</p>
<p>
    If you have found a security issue please <em>do not</em> report it in public. Instead, please follow the instructions detailed in our <a href="https://github.com/akeeba/panopticon/security/policy" target="_blank">Security Policy</a> for this software.
</p>