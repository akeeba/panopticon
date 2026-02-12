<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Corechecksums\Html $this */

$favIcon = $this->site->getFavicon(asDataUrl: true, onlyIfCached: true);
$token   = $this->container->session->getCsrfToken()->getValue();
?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ $this->site->id }}</span>
    @if($favIcon)
        <img src="{{{ $favIcon }}}"
             style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
             class="mx-1 p-1 border rounded"
             alt="">
    @endif
    <span class="flex-grow-1">{{{ $this->site->name }}}</span>
</h3>

<div class="card mt-3">
    <h4 class="card-header d-flex flex-row gap-2 align-items-center">
        <span class="fa fa-fingerprint" aria-hidden="true"></span>
        <span class="flex-grow-1">@lang('PANOPTICON_CORECHECKSUMS_TITLE')</span>
    </h4>
    <div class="card-body">
        @if ($this->lastCheck !== null)
            <div class="mb-3 text-muted">
                @sprintf('PANOPTICON_CORECHECKSUMS_LBL_LAST_CHECK', $this->getContainer()->html->basic->date('@' . $this->lastCheck))
            </div>
        @endif

        @if (empty($this->modifiedFiles))
            @if ($this->lastCheck === null)
                <div class="alert alert-info">
                    <span class="fa fa-info-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_NEVER_RUN')
                </div>
            @else
                <div class="alert alert-success">
                    <span class="fa fa-check-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_CORECHECKSUMS_LBL_ALL_CLEAN')
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                @sprintf('PANOPTICON_CORECHECKSUMS_LBL_FOUND_MODIFIED', count($this->modifiedFiles))
            </div>

            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">@lang('PANOPTICON_CORECHECKSUMS_LBL_FILE_PATH')</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($this->modifiedFiles as $i => $filePath)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <code>{{{ $filePath }}}</code>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="mt-3">
            <a href="@route(sprintf(
                            'index.php?view=sites&task=coreChecksumsEnqueue&id=%d&%s=1',
                            $this->site->getId(),
                            $token
                        ))"
               class="btn btn-primary" role="button">
                <span class="fa fa-play" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_BTN_RUN')
            </a>
        </div>
    </div>
</div>
