<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\About\Html $this */

?>
@repeatable('integrity', $hash)
    <?php
    [$algo, $encoded] = explode('-', $hash, 2);
    $decoded = str_split(bin2hex(base64_decode($encoded)), 4);
    ?>
<span class="badge bg-secondary me-1">{{{ strtoupper($algo) }}}</span>{{ implode('<wbr>', $decoded) }}
@endrepeatable

<details>
    <summary><h4 class="d-inline-block">@lang('PANOPTICON_ABOUT_LBL_3PD_SOFTWARE')</h4></summary>

    <p class="small text-muted">@lang('PANOPTICON_ABOUT_LBL_3PD_SOFTWARE_ABOUT')</p>

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
        @foreach($this->dependencies as $packageName => $packageInfo)
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
                {{ $this->npmInfo['packages']['node_modules/bootstrap']['version'] }}
            </td>
            <td>
                @yieldRepeatable('integrity', $this->npmInfo['packages']['node_modules/bootstrap']['integrity'])
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>FontAwesome</td>
            <td>
                {{ $this->npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['version'] }}
            </td>
            <td>
                @yieldRepeatable('integrity', $this->npmInfo['packages']['node_modules/@fortawesome/fontawesome-free']['integrity'])
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>Cloud9 ACE Editor</td>
            <td>
                {{ $this->npmInfo['packages']['node_modules/ace-builds']['version'] }}
            </td>
            <td>
                @yieldRepeatable('integrity', $this->npmInfo['packages']['node_modules/ace-builds']['integrity'])
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>choices.js</td>
            <td>
                {{ $this->npmInfo['packages']['node_modules/choices.js']['version'] }}
            </td>
            <td>
                @yieldRepeatable('integrity', $this->npmInfo['packages']['node_modules/choices.js']['integrity'])
            </td>
        </tr>
        <tr>
            {{-- DO NOT TRANSLATE! --}}
            <td>TinyMCE</td>
            <td>
                {{ $this->npmInfo['packages']['node_modules/tinymce']['version'] }}
            </td>
            <td>
                @yieldRepeatable('integrity', $this->npmInfo['packages']['node_modules/tinymce']['integrity'])
            </td>
        </tr>
        </tbody>
    </table>
</details>