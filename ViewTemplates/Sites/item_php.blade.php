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
use Awf\Html\Html;
use Awf\Registry\Registry;
use Awf\Text\Text;

$config = ($this->item->config instanceof Registry) ? $this->item->config : (new Registry($this->item->config));
$phpVersion = new PhpVersion();

$lastUpdateTimestamp = function () use ($config): string {
	$timestamp = $config->get('core.lastAttempt');

    return $timestamp ? $this->timeAgo($timestamp) : \Awf\Text\Text::_('PANOPTICON_LBL_NEVER');
};

$php                     = $config->get('core.php', '0.0.0');
$phpBranch               = Version::create($php)->versionFamily();
$versionInfo             = $phpVersion->getVersionInformation($php);
$latestVersionInBranch   = $versionInfo->latest;
$minimumSupportedBranch  = $phpVersion->getMinimumSupportedBranch();
$isUnknown               = $versionInfo->unknown;
$isOutOfDate             = $versionInfo->eol;
$isLatestVersionInBranch = version_compare($php, $latestVersionInBranch, 'ge');
$isLatestBranch          = $phpBranch === $phpVersion->getLatestBranch();
$isRecommendedBranch     = $phpBranch === $phpVersion->getRecommendedSupportedBranch();
$isOldestBranch          = $phpBranch === $minimumSupportedBranch;
$lastError               = trim($config->get('extensions.lastErrorMessage') ?? '');
$hasError                = !empty($lastError);

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
        <div class="small mb-3">
            @if ($lastError)
                <?php $extensionsLastErrorModalID = 'exlem-' . md5(random_bytes(120)); ?>
                <div class="btn btn-danger btn-sm px-1 py-0" aria-hidden="true"
                     data-bs-toggle="modal" data-bs-target="#{{ $extensionsLastErrorModalID }}"
                >
					<span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS')"
                          data-bs-content="{{{ $lastError }}}"></span>
                </div>

                <div class="modal fade" id="{{ $extensionsLastErrorModalID }}"
                     tabindex="-1" aria-labelledby="{{ $extensionsLastErrorModalID }}_label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h1 class="modal-title fs-5"
                                    id="{{ $extensionsLastErrorModalID }}_label">
                                    @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS')
                                </h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-break">
                                    {{{ $lastError }}}
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    @lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <span class="visually-hidden">
				    @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS') {{{ $lastError }}}
                </span>
            @endif
            <span class="{{ $hasError ? 'text-danger' : 'text-body-tertiary' }}">
                <strong>
                    @lang('PANOPTICON_SITE_LBL_JUPDATE_LAST_CHECKED')
                </strong>
                {{ $lastUpdateTimestamp() }}
            </span>
        </div>

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

            <hr/>
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
                <hr/>
                <p class="text-warning-emphasis">
                    @sprintf('PANOPTICON_SITE_LBL_PHP_NEWER_BRANCH_AVAILABLE', $this->escape($phpVersion->getLatestBranch()), $this->getContainer()->html->basic->date($versionInfo?->dates?->activeSupport?->format(DATE_RFC7231)))
                </p>
            @endif
        @endif
    </div>
</div>
