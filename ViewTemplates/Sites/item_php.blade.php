<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Registry\Registry;

$phpVersion = new PhpVersion();

$lastUpdateTimestamp     = $this->siteConfig->get('core.lastAttempt')
	? $this->timeAgo($this->siteConfig->get('core.lastAttempt'))
	: $this->getLanguage()->text('PANOPTICON_LBL_NEVER');
$php                     = $this->siteConfig->get('core.php', '0.0.0') ?: '0.0.0';
$phpBranch               = Version::create($php)?->versionFamily();
$versionInfo             = $phpVersion->getVersionInformation($php);
$latestVersionInBranch   = $versionInfo?->latest;
$minimumSupportedBranch  = $phpVersion?->getMinimumSupportedBranch();
$isUnknown               = $versionInfo?->unknown;
$isOutOfDate             = $versionInfo?->eol;
$isLatestVersionInBranch = version_compare($php, $latestVersionInBranch, 'ge');
$isLatestBranch          = $phpBranch === $phpVersion->getLatestBranch();
$isRecommendedBranch     = $phpBranch === $phpVersion->getRecommendedSupportedBranch();
$isOldestBranch          = $phpBranch === $minimumSupportedBranch;
$hasError                = !empty(trim($this->siteConfig->get('core.lastErrorMessage') ?? ''));
?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fab fa-php" aria-hidden="true"></span>
        <span class="flex-grow-1">@lang('PANOPTICON_SITE_LBL_PHP_HEAD')</span>
        <a type="button" class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&%s=1', $this->item->id, $this->container->session->getCsrfToken()->getValue()))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_BTN_PHP_RELOAD')"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_SITE_BTN_PHP_RELOAD')
            </span>
        </a>
    </h3>
    <div class="card-body">
        {{-- Last Check --}}
        <div class="small mb-3">
            <span class="{{ $hasError ? 'text-danger' : 'text-body-tertiary' }}">
                <strong>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_LAST_CHECKED')
                </strong>
                {{ $lastUpdateTimestamp }}
            </span>
        </div>

        {{-- PHP Status --}}
        @if($isUnknown)
            <div class="alert alert-info">
                <h3 class="alert-heading h5 m-0 mb-2">
                    <span class="fa fa-question-circle" aria-hidden="true"></span>
                    @sprintf('PANOPTICON_SITE_LBL_PHP_UNKNOWN', $this->escape($php))
                </h3>

                @lang('PANOPTICON_SITE_LBL_PHP_UNKNOWN_INFO')
            </div>
        @elseif($isOutOfDate)
            <div class="alert alert-danger">
                <h3 class="alert-heading h5 m-0 mb-2">
                    <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                    @sprintf('PANOPTICON_SITE_LBL_PHP_EOL', $this->escape($php))
                </h3>

                @sprintf('PANOPTICON_SITE_LBL_PHP_EOL_INFO', $this->escape($php), $this->getContainer()->html->basic->date($versionInfo?->dates?->eol?->format(DATE_RFC7231)), $this->escape($minimumSupportedBranch))
            </div>
            <details class="small">
                <summary class="fw-bold text-body-secondary">
                    <span class="fa fa-info-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_PHP_LTS_HEAD')
                </summary>
                <div class="mt-2 ps-3 pe-2 text-info" style="text-align: justify">
                    <p>
                        @lang('PANOPTICON_SITE_LBL_PHP_LTS_P1')
                    </p>
                    <p>
                        @lang('PANOPTICON_SITE_LBL_PHP_LTS_P2')
                    </p>
                    <p>
                        @sprintf('PANOPTICON_SITE_LBL_PHP_LTS_P3', $this->escape($phpVersion->getRecommendedSupportedBranch()), $this->escape($phpVersion->getLatestBranch()))
                    </p>
                </div>
            </details>
        @elseif($isOldestBranch)
            <div class="alert {{ $isLatestVersionInBranch ? 'alert-info' : 'alert-warning' }}">
                <h3 class="alert-heading h5 m-0 mb-2">
                    @if (!$isLatestVersionInBranch)
                        <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    @else
                        <span class="fa fa-check-circle" aria-hidden="true"></span>
                    @endif
                    @sprintf('PANOPTICON_SITE_LBL_PHP_PHP', $this->escape($php))
                </h3>
                @if (!$isLatestVersionInBranch)
                    @sprintf('PANOPTICON_SITE_LBL_PHP_UPDATE_AVAILABLE', $this->escape($phpBranch), $this->escape($latestVersionInBranch))
                @endif

            </div>

            <p class="text-muted">
                @sprintf('PANOPTICON_SITE_LBL_PHP_SECURITY_ONLY', $this->getContainer()->html->basic->date($versionInfo?->dates?->eol?->format(DATE_RFC7231)))
            </p>

            <hr>
            <p class="text-warning-emphasis">
                @sprintf('PANOPTICON_SITE_LBL_PHP_SHOULD_UPGRADE', $this->escape($phpVersion->getRecommendedSupportedBranch()))
            </p>
        @else
            <div class="alert {{ $isLatestVersionInBranch ? 'alert-success' : 'alert-warning' }}">
                <h3 class="alert-heading h5 m-0">
                    @if (!$isLatestVersionInBranch)
                        <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    @endif
                    @sprintf('PANOPTICON_SITE_LBL_PHP_PHP', $this->escape($php))
                </h3>
                @if (!$isLatestVersionInBranch)
                    <div class="mt-2">
                        @sprintf('PANOPTICON_SITE_LBL_PHP_UPDATE_AVAILABLE', $this->escape($phpBranch), $this->escape($latestVersionInBranch))
                    </div>
                @endif
            </div>

            <p class="text-muted">
                @sprintf('PANOPTICON_SITE_LBL_PHP_VERSION_INFO', $this->getContainer()->html->basic->date($versionInfo?->dates?->activeSupport?->format(DATE_RFC7231)), $this->getContainer()->html->basic->date($versionInfo?->dates?->eol?->format(DATE_RFC7231)))
            </p>

            @if (!$isLatestBranch)
                <hr>
                <p class="text-warning-emphasis">
                    @sprintf('PANOPTICON_SITE_LBL_PHP_NEWER_BRANCH_AVAILABLE', $this->escape($phpVersion->getLatestBranch()), $this->getContainer()->html->basic->date($versionInfo?->dates?->activeSupport?->format(DATE_RFC7231)))
                </p>
            @endif
        @endif

        {{-- Where did I get this data from? --}}
        <a href="http://endoflife.date/php" target="_blank"
           class="link-secondary link-opacity-75 link-opacity-100-hover small"
        >
            @lang('PANOPTICON_SITE_LBL_WHERE_DID_I_GET_DATA')
            <span class="fa fa-fw fa-external-link" aria-hidden="true"></span>
        </a>
    </div>
</div>
