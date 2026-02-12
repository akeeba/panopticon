<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;

/**
 * @var \Akeeba\Panopticon\View\Extensioninstall\Html $this
 */

$token = $this->container->session->getCsrfToken()->getValue();

?>

<h4>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_STEP_REVIEW')</h4>

@if (empty($this->selectedSites))
    <div class="alert alert-warning">
        <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
        @lang('PANOPTICON_EXTENSIONINSTALL_LBL_NO_SITES')
    </div>
@else

    {{-- Mixed CMS type warning --}}
    @if ($this->cmsTypeInfo['mixed'])
        <div class="alert alert-danger">
            <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_WARN_MIXED_CMS')
            <div class="mt-2">
                <a href="@route('index.php?view=extensioninstall')"
                   class="btn btn-outline-danger btn-sm">
                    <span class="fa fa-arrow-left" aria-hidden="true"></span>
                    @lang('PANOPTICON_EXTENSIONINSTALL_LBL_GOBACK')
                </a>
            </div>
        </div>
    @endif

    {{-- Mixed version warning --}}
    @if ($this->versionInfo['mixed_cms'] || $this->versionInfo['mixed_php'])
        <div class="alert alert-warning">
            <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_WARN_MIXED_VERSIONS')
        </div>
    @endif

    {{-- Selected Sites Table --}}
    <h5>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_SELECTED_SITES') ({{ count($this->selectedSites) }})</h5>

    <table class="table table-striped align-middle table-sm mb-4">
        <thead>
        <tr>
            <th>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_SITE_NAME')</th>
            <th>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_CMS_TYPE')</th>
            <th>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_CMS_VERSION')</th>
            <th>@lang('PANOPTICON_EXTENSIONINSTALL_LBL_PHP_VERSION')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->selectedSites as $site)
            <?php
            /** @var \Akeeba\Panopticon\Model\Site $site */
            $config = $site->getConfig();
            ?>
            <tr>
                <td>
                    <a class="fw-medium"
                       href="@route(sprintf('index.php?view=site&task=read&id=%s', $site->getId()))">
                        {{{ $site->name }}}
                    </a>
                    <div class="small text-muted">
                        {{{ $site->getBaseUrl() }}}
                    </div>
                </td>
                <td>
                    @if ($site->cmsType() === CMSType::JOOMLA)
                        <span class="fab fa-fw fa-joomla text-secondary" aria-hidden="true"></span>
                        Joomla!
                    @elseif ($site->cmsType() === CMSType::WORDPRESS)
                        <span class="fab fa-fw fa-wordpress text-info" aria-hidden="true"></span>
                        WordPress
                    @endif
                </td>
                <td>{{{ $config->get('core.current.version') }}}</td>
                <td>
                    <span class="fab fa-fw fa-php text-primary" aria-hidden="true"></span>
                    {{{ $config->get('core.php') }}}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Install Form --}}
    @unless($this->cmsTypeInfo['mixed'])
        <form action="@route('index.php?view=extensioninstall&task=install')" method="post"
              enctype="multipart/form-data" id="installForm">

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">@lang('PANOPTICON_EXTENSIONINSTALL_LBL_URL_OR_FILE')</h5>

                    {{-- URL input --}}
                    <div class="mb-3">
                        <label for="url" class="form-label fw-bold">
                            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_URL')
                        </label>
                        <input type="url" class="form-control" id="url" name="url"
                               placeholder="https://example.com/extension.zip">
                        <div class="form-text">
                            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_URL_HELP')
                        </div>
                    </div>

                    <div class="text-center my-2 text-muted">
                        &mdash; or &mdash;
                    </div>

                    {{-- File upload --}}
                    <div class="mb-3">
                        <label for="package_file" class="form-label fw-bold">
                            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_FILE')
                        </label>
                        <input type="file" class="form-control" id="package_file" name="package_file"
                               accept=".zip,.tar,.tar.gz,.tgz,.tar.bz2,.tbz2">
                        <div class="form-text">
                            @lang('PANOPTICON_EXTENSIONINSTALL_LBL_FILE_HELP')
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="@route('index.php?view=extensioninstall')" class="btn btn-secondary">
                    <span class="fa fa-arrow-left" aria-hidden="true"></span>
                    @lang('PANOPTICON_EXTENSIONINSTALL_LBL_GOBACK')
                </a>
                <button type="submit" class="btn btn-danger">
                    <span class="fa fa-download" aria-hidden="true"></span>
                    @lang('PANOPTICON_EXTENSIONINSTALL_LBL_INSTALL')
                </button>
            </div>

            <input type="hidden" name="token" value="@token()">
        </form>
    @endunless
@endif
