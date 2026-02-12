<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$token         = $this->container->session->getCsrfToken()->getValue();
$lastCheck     = $this->coreChecksumsLastCheck;
$lastStatus    = $this->coreChecksumsLastStatus;
$modifiedCount = $this->coreChecksumsModifiedCount;
?>

<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-fingerprint" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_HEAD')
        </span>
        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardCoreChecksumsBody"
                aria-expanded="true" aria-controls="cardCoreChecksumsBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body collapse show" id="cardCoreChecksumsBody">
        @if ($lastCheck === null)
            {{-- No check has been run yet --}}
            <div class="alert alert-info">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_NEVER_RUN')
            </div>
        @elseif ($lastStatus === true)
            {{-- Last check was clean --}}
            <div class="alert alert-success">
                <span class="fa fa-check-circle" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_CORECHECKSUMS_CLEAN', $this->getContainer()->html->basic->date('@' . $lastCheck))
            </div>
        @else
            {{-- Modified files found --}}
            <div class="alert alert-warning">
                <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                @sprintf('PANOPTICON_SITE_LBL_CORECHECKSUMS_MODIFIED', $modifiedCount, $this->getContainer()->html->basic->date('@' . $lastCheck))
            </div>
            <div class="mb-3">
                <a href="@route(sprintf('index.php?view=corechecksums&site_id=%d', $this->item->getId()))"
                   class="btn btn-outline-warning btn-sm" role="button">
                    <span class="fa fa-list" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_BTN_DETAILS')
                </a>
            </div>
        @endif

        <div class="d-flex flex-row gap-2">
            <a href="@route(sprintf(
                            'index.php?view=sites&task=coreChecksumsEnqueue&id=%d&%s=1',
                            $this->item->getId(),
                            $token
                        ))"
               class="btn btn-primary btn-sm" role="button">
                <span class="fa fa-play" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_BTN_RUN')
            </a>
            <a href="@route(sprintf('index.php?view=checksumtasks&site_id=%d&manual=0', $this->item->getId()))"
               class="btn btn-secondary btn-sm" role="button">
                <span class="fa fa-clock" aria-hidden="true"></span>
                @lang('PANOPTICON_SITE_LBL_CORECHECKSUMS_BTN_SCHEDULE')
            </a>
        </div>
    </div>
</div>
