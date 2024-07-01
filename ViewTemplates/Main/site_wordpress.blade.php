<?php

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\WordPressUpdateRunState;
use Akeeba\Panopticon\Library\SoftwareVersions\WordPressVersion;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;
use Awf\Uri\Uri;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$wpVersion       = $config->get('core.current.version');
$stability       = $config->get('core.current.stability');
$canUpgrade      = $config->get('core.canUpgrade');
$latestWPVersion = $config->get('core.latest.version');
$lastError       = trim($config->get('core.lastErrorMessage') ?? '');
$wpRunState      = $item->getWordPressUpdateRunState();
$wpUpdateFailure = !$config->get('core.extensionAvailable', true)
                   || !$config->get('core.updateSiteAvailable', true);
$token           = $this->container->session->getCsrfToken()->getValue();
$returnUrl       = base64_encode(Uri::getInstance()->toString());
$wpVersionHelper = new WordPressVersion($this->getContainer());

// TODO
?>

@repeatable('wpVersion', $wpVersion)
{{{ $wpVersion }}}
<?php
$version = Version::create($wpVersion ?? '0.0.0') ?>
@if($version->isDev())
    <sup>
        <span class="badge bg-danger">
            <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_DEV_SHORT')</span>
            <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_DEV_LONG')</span>
        </span>
    </sup>
@elseif($version->isAlpha())
    <sup>
        <span class="badge bg-danger">
            <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_SHORT')</span>
            <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_LONG')</span>
        </span>
    </sup>
@elseif($version->isBeta())
    <sup>
        <span class="badge text-bg-warning">
            <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_SHORT')</span>
            <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_LONG')</span>
        </span>
    </sup>
@elseif($version->isRC())
    <sup>
        <span class="badge bg-info">
            <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_RC_SHORT')</span>
            <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_RC_LONG')</span>
        </span>
    </sup>
@else
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_STABLE_LONG')</span>
@endif
@endrepeatable

@repeatable('wpLogo')
<span class="fab fa-wordpress" aria-hidden="true"
      data-bs-toggle="tooltip" data-bs-placement="bottom"
      data-bs-title="WordPress"
></span>
<span class="visually-hidden">WordPress</span>
@endrepeatable

<div class="d-flex flex-row gap-2">
    {{-- Button to reload the site information --}}
    <a class="btn btn-sm btn-outline-secondary" role="button"
       href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&return=%s&%s=1', $item->id, $returnUrl, $token))"
       data-bs-toggle="tooltip" data-bs-placement="bottom"
       data-bs-title="@lang('PANOPTICON_SITE_BTN_CMSUPDATE_RELOAD')"
    >
        <span class="fa fa-refresh" aria-hidden="true"></span>
        <span class="visually-hidden">@sprintf('PANOPTICON_SITE_BTN_CMSUPDATE_RELOAD_SR', $item->name)</span>
    </a>

    {{-- Did we have an error last time we tried to update the site information? --}}
    @if ($lastError)
			<?php
			$siteInfoLastErrorModalID = 'silem-' . md5(random_bytes(120)); ?>
        <div>
            <div class="btn btn-danger btn-sm" aria-hidden="true"
                 data-bs-toggle="modal" data-bs-target="#{{ $siteInfoLastErrorModalID }}"
            >
                <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"
                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                      data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO')"
                      data-bs-content="{{{ $lastError }}}"></span>
            </div>

            <div class="modal fade" id="{{ $siteInfoLastErrorModalID }}"
                 tabindex="-1" aria-labelledby="{{ $siteInfoLastErrorModalID }}_label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5"
                                id="{{ $siteInfoLastErrorModalID }}_label">
                                @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO')
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
                @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_SITEINFO') {{{ $lastError }}}
            </span>
        </div>
    @endif

    {{-- Is Wordpress update working at all? --}}
    @if($wpUpdateFailure)
        <div>
            <span class="fa fa-exclamation-triangle text-danger" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_WORDPRESS_UPDATES_BROKEN')"
            ></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_MAIN_SITES_LBL_WORDPRESS_UPDATES_BROKEN')
            </span>
        </div>
    @elseif ($wpRunState === WordPressUpdateRunState::ERROR)
        <div>
            <div class="badge text-bg-danger py-2"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE_WP')"
            >
                <span class="fa fa-fw fa-circle-xmark" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE_WP')</span>
            </div>
        </div>
    @elseif($wpRunState === WordPressUpdateRunState::RUNNING)
        <div>
            <div class="badge bg-info-subtle text-primary"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE_WP')"
            >
                <span class="fa fa-play" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE_WP')</span>
            </div>
        </div>
    @elseif ($wpRunState === WordPressUpdateRunState::SCHEDULED)
        <div>
            <div class="badge bg-info-subtle text-info"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE_WP')"
            >
                <span class="fa fa-clock" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE_WP')</span>
            </div>
        </div>
    @endif

    {{-- WordpPress version --}}
    @if (empty($wpVersion))
        <div>
            <span class="badge bg-secondary-subtle text-dark">@lang('PANOPTICON_MAIN_SITES_LBL_JVERSION_UNKNOWN')</span>
        </div>
    @else
        @if ($canUpgrade)
            <div
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPGRADABLE_TO', $latestWPVersion)"

            >
                <div class="text-warning fw-bold d-inline-block">
                    @yieldRepeatable('wpLogo')
                    @yieldRepeatable('wpVersion', $wpVersion)
                </div>
                <div class="small text-success-emphasis d-inline-block">
                    <span class="fa fa-arrow-right" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
                    @yieldRepeatable('wpLogo')
                    @yieldRepeatable('wpVersion', $latestWPVersion)
                </div>
            </div>
        @else
            @if($wpUpdateFailure)
                <div class="text-secondary">
                    @yieldRepeatable('wpLogo')
                    @yieldRepeatable('wpVersion', $wpVersion)
                </div>
            @elseif($wpVersionHelper->isEOL($wpVersion))
                <div class="text-danger-emphasis">
                    @yieldRepeatable('wpLogo')
                    <span class="fa fa-fw fa-skull" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_WP_EOL')"
                    ></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_WP_EOL')</span>
                    @yieldRepeatable('wpVersion', $wpVersion)
                </div>
            @else
                <div class="text-body">
                    @yieldRepeatable('wpLogo')
                    @yieldRepeatable('wpVersion', $wpVersion)
                </div>
            @endunless
        @endif
    @endif


</div>