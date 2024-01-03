<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\JoomlaVersion\JoomlaVersion;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$jVersion            = $config->get('core.current.version');
$stability           = $config->get('core.current.stability');
$canUpgrade          = $config->get('core.canUpgrade');
$latestJoomlaVersion = $config->get('core.latest.version');
$lastError           = trim($config->get('core.lastErrorMessage') ?? '');
$jUpdateFailure      = !$config->get('core.extensionAvailable', true) || !$config->get('core.updateSiteAvailable', true);
$token               = $this->container->session->getCsrfToken()->getValue();
$returnUrl           = base64_encode(\Awf\Uri\Uri::getInstance()->toString());
$jVersionHelper      = new JoomlaVersion($this->getContainer());
?>

@repeatable('joomlaVersion', $jVersion)
{{{ $jVersion }}}
<?php $version = Version::create($jVersion) ?>
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
            <span class="badge bg-warning">
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

@repeatable('joomlaLogo')
    <span class="fab fa-joomla" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="bottom"
          data-bs-title="Joomla!&reg;"
    ></span>
    <span class="visually-hidden">Joomla!&reg;</span>
@endrepeatable

<div class="d-flex flex-row gap-2">
    {{-- Button to reload the site information --}}
    <a class="btn btn-sm btn-outline-secondary" role="button"
       href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&return=%s&%s=1', $item->id, $returnUrl, $token))"
       data-bs-toggle="tooltip" data-bs-placement="bottom"
       data-bs-title="@lang('PANOPTICON_SITE_BTN_JUPDATE_RELOAD')"
    >
        <span class="fa fa-refresh" aria-hidden="true"></span>
        <span class="visually-hidden">@sprintf('PANOPTICON_SITE_BTN_JUPDATE_RELOAD_SR', $item->name)</span>
    </a>

    {{-- Did we have an error last time we tried to update the site information? --}}
    @if ($lastError)
        <?php $siteInfoLastErrorModalID = 'silem-' . md5(random_bytes(120)); ?>
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

    {{-- Is Joomla Update working at all? --}}
    @if($jUpdateFailure)
        <div>
            <span class="fa fa-exclamation-triangle text-danger" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')"
            ></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
            </span>
        </div>
    @elseif ($config->get('core.canUpgrade', false) && $item->isJoomlaUpdateTaskStuck())
        <div>
            <div class="badge bg-light text-dark"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')"
            >
                <span class="fa fa-bell" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')</span>
            </div>
        </div>
    @elseif($config->get('core.canUpgrade', false) && $item->isJoomlaUpdateTaskRunning())
        <div>
            <div class="badge bg-info-subtle text-primary"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE')"
            >
                <span class="fa fa-play" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE')</span>
            </div>
        </div>
    @elseif ($config->get('core.canUpgrade', false) && $item->isJoomlaUpdateTaskScheduled())
        <div>
            <div class="badge bg-info-subtle text-info"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')"
            >
                <span class="fa fa-clock" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')</span>
            </div>
        </div>
    @endif

    {{-- Joomla! version --}}
    @if (empty($jVersion))
        <span class="badge bg-secondary-subtle">@lang('PANOPTICON_MAIN_SITES_LBL_JVERSION_UNKNOWN')</span>
    @else
        @if ($canUpgrade)
            <div
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPGRADABLE_TO', $latestJoomlaVersion)"

            >
                <div class="text-warning fw-bold d-inline-block">
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
                <div class="small text-success-emphasis d-inline-block">
                    <span class="fa fa-arrow-right" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $latestJoomlaVersion)
                </div>
            </div>
        @else
            @if($jUpdateFailure)
                <div class="text-secondary">
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @elseif($jVersionHelper->isEOLMajor($jVersion))
                <div class="text-danger-emphasis">
                    <span class="fa fa-fw fa-skull" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_EOL_MAJOR')"
                    ></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_EOL_MAJOR')</span>
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @elseif($jVersionHelper->isEOLBranch($jVersion))
                <div class="text-danger">
                    <span class="fa fa-fw fa-book-dead" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_EOL_BRANCH')"
                    ></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_EOL_BRANCH')</span>
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @else
                <div class="text-body">
                    @yieldRepeatable('joomlaLogo')
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @endunless
        @endif

    @endif

    {{-- Report number of changed template overrides --}}
    @if (($overridesChanged = $config->get('core.overridesChanged')) > 0)
        <div class="ms-2 small" data-bs-toggle="tooltip" data-bs-placement="bottom"
             data-bs-title="@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)">
            <span class="badge bg-light-subtle text-warning border border-warning-subtle">
                <span class="fa fa-arrows-to-eye fa-fw" aria-hidden="true"></span>
                <span aria-hidden="true">{{ $overridesChanged ?? 0 }}</span>
                <span class="visually-hidden">@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)</span>
            </span>
        </div>
    @endif

</div>